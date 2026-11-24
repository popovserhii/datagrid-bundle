<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2025 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Popov
 * @package Popov_<package>
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Column;

use Doctrine\ORM\Query\Expr;
use InvalidArgumentException;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Literal;
use Popov\DatagridBundle\Type\JsonObject;
use RuntimeException;
use ZfcDatagrid\Column;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Type;

class ObjectColumn extends Column\Select
{
    /**
     * Column position in a response
     *
     * @var int
     */
    protected $defaultPosition = 0;

    /**
     * @var array
     */
    protected $positions = [];

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @return Type\AbstractType
     */
    public function getType(): Type\AbstractType
    {
        if (null === $this->type) {
            $this->type = new JsonObject();
        }

        return $this->type;
    }

    /**
     * Add a column by array config or instanceof Column\AbstractColumn.
     *
     * @param array|Column\AbstractColumn $col
     *
     * @return $this
     */
    public function addColumn($col): self
    {
        #if (!$col instanceof Column\AbstractColumn) {
        #    $col = $this->createColumn($col);
        #}

        if (null === $col->getPosition()) {
            $col->setPosition($this->defaultPosition);
        }

        $this->columns[$col->getUniqueId()] = $col;
        $this->positions[$col->getPosition()][$col->getUniqueId()] = $col;

        return $this;
    }

    /**
     * @return AbstractColumn[]
     */
    public function sortColumns(): array
    {
        ksort($this->positions);

        $columns = [];
        foreach ($this->positions as $position => $column) {
            $columns += $column;
        }

        return $this->columns = $columns;
    }

    /**
     * @return AbstractColumn[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    public function getSelectPart1()
    {
        [$parts, $type] = $this->getSelectExpression();

        return $type === Expr\Select::class
            ? new Expr\Select(implode('', $parts))
            : $this->composeSql($parts);
    }

    public function getSelectPart2()
    {
        return '';
    }

    public function buildSqlJson()
    {
        [$parts, $type] = self::getSelectExpression();
        if ($type === Expr\Select::class) {
            throw new InvalidArgumentException('Doctrine expressions cannot be rendered as Laminas SQL.');
        }

        return $this->composeSql($parts)->getExpression();
    }

    /**
     * Use the first field's source for both the null check and the JSON value.
     *
     * @return array{list<string|Expression|Expr\Select|Expr\Func>, class-string|null}
     */
    protected function getSelectExpression(): array
    {
        $type = null;
        $fields = [];
        $identity = [];
        foreach ($this->getColumns() as $column) {
            [$entityName, $fieldName] = $this->getUniqueParts($column);
            if ($fields === [] && ($entityName === '' || $fieldName === '')) {
                break;
            }

            [$source, $childType] = $column instanceof self
                ? $column->getSelectExpression()
                : $this->getFieldExpression($column);
            if ($type !== null && $childType !== null && $type !== $childType) {
                throw new InvalidArgumentException('Cannot mix Doctrine and Laminas expressions in a nested column.');
            }

            $type ??= $childType;
            if ($fields === []) {
                $identity = $source;
            } else {
                $fields[] = ', ';
            }

            $fields[] = "'" . str_replace("'", "''", $fieldName) . "', ";
            array_push($fields, ...$source);
        }

        if ($fields === []) {
            throw new RuntimeException('The first nested column must have an ID such as customer_id or project_id.');
        }

        return [
            ['CASE WHEN ', ...$identity, ' IS NOT NULL THEN JSON_OBJECT(', ...$fields, ') ELSE NULLIF(1,1) END'],
            $type,
        ];
    }

    /**
     * Keep custom field sources in the caller's query language.
     *
     * @todo Move Select source handling to zfc-datagrid.
     */
    private function getFieldExpression(AbstractColumn $column): array
    {
        $source = $column->getSelectPart1();
        if (is_string($source) && $column->getSelectPart2() != '') {
            $source .= '.' . $column->getSelectPart2();
        }

        $type = match (true) {
            $source instanceof Expression => Expression::class,
            $source instanceof Expr\Select, $source instanceof Expr\Func => Expr\Select::class,
            is_string($source) => null,
            default => throw new InvalidArgumentException('Unsupported source for a nested Select column.'),
        };

        return [[$source], $type];
    }

    /** Leave parameter binding to Laminas when an expression is embedded in JSON. */
    private function composeSql(array $parts): Expression
    {
        $sql = '';
        $parameters = [];
        foreach ($parts as $part) {
            if ($part instanceof Expression && (array) $part->getParameters() !== []) {
                $parameters[] = $part;
                $sql .= '?';
                continue;
            }

            $text = $part instanceof Expression ? $part->getExpression() : $part;
            if (str_contains($text, '?')) {
                $parameters[] = new Literal($text);
                $sql .= '?';
                continue;
            }

            $sql .= $text;
        }

        return new Expression($sql, $parameters);
    }

    protected function getUniqueParts(AbstractColumn $column)
    {
        return array_pad(explode('_', rtrim($column->getUniqueId(), '_'), 2), 2, '');
    }
}
