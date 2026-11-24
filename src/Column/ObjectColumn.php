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
        [$sql, $doctrine] = $this->getSelectExpression();

        return $doctrine ? new Expr\Select($sql) : new Expression($sql);
    }

    public function getSelectPart2()
    {
        return '';
    }

    public function buildSqlJson()
    {
        [$sql, $doctrine] = $this->getSelectExpression();
        if ($doctrine) {
            throw new InvalidArgumentException('Doctrine expressions cannot be rendered as Laminas SQL.');
        }

        return $sql;
    }

    /**
     * Build JSON from the configured Select sources and keys.
     *
     * @todo Move Select source handling to zfc-datagrid.
     */
    protected function getSelectExpression(): array
    {
        $fields = [];
        $identity = null;
        $doctrine = false;

        foreach ($this->getColumns() as $column) {
            [$entityName, $fieldName] = $this->getUniqueParts($column);
            if ($identity === null && ($entityName === '' || $fieldName === '')) {
                throw new RuntimeException('The first nested column must have an ID such as customer_id or project_id.');
            }

            $source = $column->getSelectPart1();
            if (is_string($source)) {
                if ($column->getSelectPart2() != '') {
                    $source .= '.' . $column->getSelectPart2();
                }
            } else {
                $doctrine = $doctrine || $source instanceof Expr\Select || $source instanceof Expr\Func;
                $source = $source instanceof Expression ? $source->getExpression() : (string) $source;
            }

            $identity ??= $source;
            $fields[] = "'" . str_replace("'", "''", $fieldName) . "', " . $source;
        }

        if ($identity === null) {
            throw new RuntimeException('The first nested column must have an ID such as customer_id or project_id.');
        }

        return [
            'CASE WHEN ' . $identity . ' IS NOT NULL THEN JSON_OBJECT(' . implode(', ', $fields)
                . ') ELSE NULLIF(1,1) END',
            $doctrine,
        ];
    }

    protected function getUniqueParts(AbstractColumn $column)
    {
        return array_pad(explode('_', rtrim($column->getUniqueId(), '_'), 2), 2, '');
    }
}
