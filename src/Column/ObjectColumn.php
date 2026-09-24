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

use ZfcDatagrid\Column;
use Doctrine\ORM\Query\Expr;
use Laminas\Db\Sql\Expression;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\Datagrid;
use ZfcDatagrid\Column\AbstractColumn;
use MediaparkLt\CustomerBundle\Entity\Customer;
use MediaparkLt\ProjectBundle\Entity\Status;

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
        $this->setType(new JsonableArray());

        $sql = $this->buildSqlJson();

        //return new Expr\Select($sql); // @todo-serhii Implement support for both, Doctrine and LaminasTable
        return new Expression($sql);
    }

    public function getSelectPart2()
    {
        return '';
    }

    public function buildSqlJson()
    {
        $this->setType(new JsonableArray());

        $selectColumns = $this->getSelectColumns();
        [$gridId, $primaryName] = $this->getPrimaries();

        $sql = "
            CASE WHEN {$gridId}.{$primaryName} IS NOT NULL THEN 
                JSON_OBJECT(" . implode(', ', $selectColumns) . ") 
            ELSE NULLIF(1,1) END
        ";

        return $sql;
    }

    protected function getSelectColumns()
    {
        $selectColumns = [];
        foreach ($this->getColumns() as $column) {
            $colString = $column->getSelectPart1();
            if ($column->getSelectPart2() != '') {
                $colString .= '.' . $column->getSelectPart2();
            }

            [$entityName, $fieldName] = $this->getUniqueParts($column);

            if ($colString instanceof Expression) {
                $colString = $colString->getExpression();
            }
            $selectColumns[] = "'{$fieldName}'" . ', ' . $colString;
        }

        return $selectColumns;
    }

    /**
     * Determine base grid ID to build appropriate JSON hierarchy
     *
     * First part equals to the Grid ID
     * Second part equals to the Primary name (aka, field ID)
     *
     * @return array
     */
    protected function getPrimaries()
    {
        $gridId = '';
        foreach ($this->getColumns() as $column) {
            [$gridId, $primaryName] = $this->getUniqueParts($column);

            break;
        }

        if (empty($primaryName)) {
            throw new \RuntimeException('Grid ID is not defined. Your grid must have at least one column with entity name as prefix (e.g. customer_id, project_id, etc.).');
        }

        return [$gridId, $primaryName];
    }

    protected function getUniqueParts(AbstractColumn $column)
    {
        $uniqueId = rtrim($column->getUniqueId(), '_');
        $entityName = strtok($uniqueId, '_');
        $fieldName = substr($uniqueId, strlen($entityName) + 1);

        return [$entityName, $fieldName];
    }
}
