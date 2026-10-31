<?php

namespace Popov\DatagridBundle\DataSource\Remote;

use InvalidArgumentException;
use Popov\DatagridBundle\Renderer\Json\Renderer;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\DataSource\AbstractDataSource;
use ZfcDatagrid\Filter;
use ZfcDatagrid\FilterGroup;

/** Builds public listing parameters from the renderer's final conditions. */
class Request
{
    public function encode(AbstractDataSource $dataSource): array
    {
        $query = [
            'filters' => json_encode(
                $this->filters($dataSource->getFilterGroup()),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ];
        $columns = [];
        $directions = [];
        foreach ($dataSource->getSortConditions() as $condition) {
            $direction = strtolower($condition['sortDirection']);
            if (!in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException('Invalid remote sort direction: ' . $direction);
            }
            $columns[] = $this->fieldName($condition['column']);
            $directions[] = $direction;
        }
        if ($columns) {
            $query['sortBy'] = implode(',', $columns);
            $query['sortOrder'] = implode(',', $directions);
        }
        $groups = [];
        foreach ($dataSource->getGroupConditions() as $column) {
            $groups[] = $this->fieldName($column);
        }
        if ($groups) {
            $query['groupBy'] = implode(',', $groups);
        }

        return $query;
    }

    public function fieldName(AbstractColumn $column): string
    {
        if (!$column instanceof Select) {
            throw new InvalidArgumentException('This column cannot be forwarded: ' . $column->getUniqueId());
        }
        $options = $column->getRendererParameters('remote');
        $field = $options['field'] ?? null;
        if (null === $field) {
            $part1 = $column->getSelectPart1();
            $part2 = $column->getSelectPart2();
            if (is_string($part1)) {
                $field = '' !== (string) $part2 ? $part1 . '.' . $part2 : $part1;
            } else {
                // Computed SQL columns are addressed by their public identifier.
                $field = implode('.', explode('_', $column->getUniqueId(), 2));
            }
        }
        if (!is_string($field) || !preg_match('/\A[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*\z/', $field)) {
            throw new InvalidArgumentException('Invalid remote field: ' . $column->getUniqueId());
        }

        return $field;
    }

    private function filters($group): array
    {
        $result = ['groupOp' => FilterGroup::COND_AND, 'rules' => []];
        if (!$group instanceof FilterGroup) {
            return $result;
        }
        if (!in_array($group->getCondition(), [FilterGroup::COND_AND, FilterGroup::COND_OR], true)) {
            throw new InvalidArgumentException('Invalid remote filter group condition.');
        }
        $result['groupOp'] = $group->getCondition();
        $operators = array_flip(Renderer::MATCHING_OPERATORS);
        foreach ($group->getFilters() as $filter) {
            if (!isset($operators[$filter->getOperator()])) {
                throw new InvalidArgumentException('Unsupported remote filter operator: ' . $filter->getOperator());
            }
            $result['rules'][] = [
                'field' => $this->fieldName($filter->getColumn()),
                'op' => $operators[$filter->getOperator()],
                'data' => $this->value($filter),
            ];
        }
        foreach ($group->getGroups() as $child) {
            $converted = $this->filters($child);
            // Ignored rules can leave empty subgroups; they must not become new conditions.
            if ($converted['rules'] || !empty($converted['groups'])) {
                $result['groups'][] = $converted;
            }
        }

        return $result;
    }

    private function value(Filter $filter): string
    {
        // The receiver parses public values again. Preserve their input format,
        // especially dates/timezones and localized numbers, rather than sending SQL values.
        $display = $filter->getDisplayColumnValue();
        if ('' !== $display || in_array($filter->getOperator(), [Filter::NULL, Filter::NOT_NULL], true)) {
            return $display;
        }

        // Also support programmatically constructed filters that only expose parsed values.
        return implode(Filter::BETWEEN === $filter->getOperator() ? ' <> ' : ',', $filter->getValues());
    }
}
