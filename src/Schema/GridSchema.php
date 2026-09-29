<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2026 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @package Popov_DatagridBundle
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Schema;

use InvalidArgumentException;
use JsonException;
use ReflectionProperty;
use ZfcDatagrid\Column;
use ZfcDatagrid\Column\Action\AbstractAction;
use ZfcDatagrid\Column\Formatter\AbstractFormatter;
use ZfcDatagrid\Column\Style\AbstractColor;
use ZfcDatagrid\Column\Style\AbstractStyle;
use ZfcDatagrid\Datagrid;

/**
 * Creates a renderer-independent description of a ZfcDatagrid grid.
 *
 * Renderer options intentionally remain opaque. Consumers must interpret the
 * values in renderer_parameters according to their named contract.
 */
class GridSchema
{
    private const VERSION = '1.0';

    /**
     * @param object $grid A Datagrid or an object exposing getDatagrid().
     *
     * @return array<string, mixed>
     */
    public function create($grid): array
    {
        $datagrid = $this->resolveDatagrid($grid);

        return [
            'schema_version' => self::VERSION,
            'grid' => [
                'id' => $datagrid->getId(),
                'title' => $datagrid->getTitle(),
                'default_items_per_page' => $datagrid->getDefaultItemsPerPage(),
                'filterable' => $datagrid->isUserFilterEnabled(),
                'parameters' => $datagrid->getParameters(),
                'row_styles' => $this->rowStyles($datagrid),
                'row_click_action' => $this->action($datagrid),
                'has_mass_actions' => $datagrid->hasMassAction(),
            ],
            'columns' => $this->columns($datagrid),
        ];
    }

    /**
     * @param object $grid
     *
     * @throws JsonException
     */
    public function createJson($grid, int $flags = 0): string
    {
        return json_encode(
            $this->create($grid),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $flags
        );
    }

    protected function resolveDatagrid($grid): Datagrid
    {
        if ($grid instanceof Datagrid) {
            return $grid;
        }

        if (!is_object($grid) || !method_exists($grid, 'getDatagrid')) {
            throw new InvalidArgumentException('Expected a ZfcDatagrid\\Datagrid or an object with getDatagrid().');
        }

        $datagrid = $grid->getDatagrid();
        if (!$datagrid instanceof Datagrid) {
            throw new InvalidArgumentException('getDatagrid() must return a ZfcDatagrid\\Datagrid.');
        }

        return $datagrid;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function columns(Datagrid $datagrid): array
    {
        $columns = [];
        foreach ($datagrid->sortColumns() as $column) {
            $columns[] = $this->column($column);
        }

        return $columns;
    }

    /**
     * @return array
     */
    protected function column(Column\AbstractColumn $column): array
    {
        return [
            'id' => (string) $column->getUniqueId(),
            'label' => $column->getLabel(),
            'type' => $column->getType()->getTypeName(),
            'position' => $column->getPosition(),
            'width' => $column->getWidth(),
            'hidden' => $column->isHidden(),
            'skipped' => $column->isSkipped(),
            'identity' => $column->isIdentity(),
            'row_click_enabled' => $column->isRowClickEnabled(),
            'sortable' => $column->isUserSortEnabled(),
            'sort' => [
                'default' => $column->hasSortDefault() ? $column->getSortDefault() : null,
                'active_direction' => $column->isSortActive() ? $column->getSortActiveDirection() : null,
            ],
            'filter' => [
                'enabled' => $column->isUserFilterEnabled(),
                'default_value' => $column->hasFilterDefaultValue() ? $column->getFilterDefaultValue() : null,
                'default_operator' => $column->getFilterDefaultOperation(),
                'select_options' => $column->hasFilterSelectOptions() ? $column->getFilterSelectOptions() : null,
                'active_value' => $column->isFilterActive() ? $column->getFilterActiveValue() : null,
            ],
            'translation_enabled' => $column->isTranslationEnabled(),
            'replace_values' => $column->hasReplaceValues() ? $column->getReplaceValues() : null,
            'styles' => $this->styles($column),
            'formatters' => $this->formatters($column),
            'actions' => $this->actions($column),
            'renderer_parameters' => $this->rendererParameters($column),
        ];
    }

    /**
     * ZfcDatagrid exposes one renderer contract at a time. Reading the
     * protected store is the only way to preserve arbitrary contracts such as
     * jqGrid and elasticSearch without knowing their names in advance.
     *
     * @return array
     */
    protected function rendererParameters(Column\AbstractColumn $column): array
    {
        if (method_exists($column, 'getAllRendererParameters')) {
            return $column->getAllRendererParameters();
        }

        $property = new ReflectionProperty(Column\AbstractColumn::class, 'rendererParameter');
        $property->setAccessible(true);

        return $property->getValue($column);
    }

    /**
     * @return array
     */
    protected function rowStyles(Datagrid $datagrid): array
    {
        $styles = [];
        foreach ($datagrid->getRowStyles() as $style) {
            $styles[] = $this->style($style);
        }

        return $styles;
    }
    /**
     * @return array
     */
    protected function styles(Column\AbstractColumn $column): array
    {
        $styles = [];
        foreach ($column->getStyles() as $style) {
            $styles[] = $this->style($style);
        }

        return $styles;
    }
    
    protected function style(AbstractStyle $style): array
    {
        $schema = [
            'type' => $this->typeName($style),
            'conditions_operator' => $style->getByValueOperator(),
            'conditions' => [],
        ];

        foreach ($style->getByValues() as $rule) {
            $schema['conditions'][] = [
                'column' => (string) $rule['column']->getUniqueId(),
                'operator' => $rule['operator'],
                'value' => $rule['value'] instanceof Column\AbstractColumn
                    ? ['column' => (string) $rule['value']->getUniqueId()]
                    : $rule['value'],
            ];
        }

        if ($style instanceof Column\Style\Align) {
            $schema['alignment'] = $style->getAlignment();
        } elseif ($style instanceof AbstractColor) {
            $schema['color'] = '#' . $style->getRgbHexString();
        } elseif ($style instanceof Column\Style\CSSClass) {
            $schema['class'] = $style->getClass();
        }

        return $schema;
    }

    /**
     * @return array
     */
    protected function formatters(Column\AbstractColumn $column): array
    {
        $formatters = [];
        foreach ($column->getFormatters() as $formatter) {
            $formatters[] = [
                'type' => $this->typeName($formatter),
                'valid_renderer_names' => $formatter->getValidRendererNames(),
            ];
        }

        return $formatters;
    }

    /** 
     * @return array 
     */
    protected function actions(Column\AbstractColumn $column): array
    {
        if (!($column instanceof Column\Action)) {
            return [];
        }

        $actions = [];
        foreach ($column->getActions() as $action) {
            $actions[] = $this->action($action);
        }

        return $actions;
    }

    /** 
     * @return array 
     */
    protected function action(Datagrid $datagrid): ?array
    {
        if (!($action = $datagrid->getRowClickAction())) {
            return null;
        }

        $schema = [
            'type' => $this->typeName($action),
            'attributes' => $action->getAttributes(),
            'route' => $action->getRoute(),
            'route_params' => $action->getRouteParams(),
            'visibility_operator' => $action->getShowOnValueOperator(),
            'visibility_conditions' => [],
        ];

        foreach ($action->getShowOnValues() as $rule) {
            $schema['visibility_conditions'][] = [
                'column' => (string) $rule['column']->getUniqueId(),
                'operator' => $rule['comparison'],
                'value' => $rule['value'] instanceof Column\AbstractColumn
                    ? ['column' => (string) $rule['value']->getUniqueId()]
                    : $rule['value'],
            ];
        }

        if ($action instanceof Column\Action\Button) {
            $label = $action->getLabel();
            $schema['label'] = $label instanceof Column\AbstractColumn
                ? ['column' => (string) $label->getUniqueId()]
                : $label;
        } elseif ($action instanceof Column\Action\Icon) {
            $schema['icon_class'] = $action->getIconClass();
            $schema['icon_link'] = $action->getIconLink();
        }

        return $schema;
    }

    protected function typeName($object): string
    {
        $class = get_class($object);
        $parts = explode('\\', $class);

        return lcfirst(end($parts));
    }
}
