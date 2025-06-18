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
     * @return \ZfcDatagrid\Column\AbstractColumn[]
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
     * @return \ZfcDatagrid\Column\AbstractColumn[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    public function getSelectPart1()
    {
        $this->setType(new JsonableArray());

        $gridId = '';
        $index = 0;
        $selectColumns = [];
        foreach ($this->getColumns() as $column) {
            $colString = $column->getSelectPart1();
            if ($column->getSelectPart2() != '') {
                $colString .= '.' . $column->getSelectPart2();
            }

            $entityName = strtok($column->getUniqueId(), '_');
            $fieldName = substr($column->getUniqueId(), strlen($entityName) + 1);

            // Determine base grid ID to build appropriate JSON hierarchy
            if (0 === $index && !$gridId) {
                $gridId = $entityName;
                $primaryName = $fieldName;
            }

            $selectColumns[] = "'{$fieldName}'" . ', ' . $colString;
            $index++;
        }

        if (!$gridId) {
            throw new \RuntimeException('Grid ID is not defined. Your grid must have at least one column with entity name as prefix (e.g. customer_id, project_id, etc.).');
        }

        $sql = "CASE WHEN {$gridId}.{$primaryName} IS NOT NULL THEN JSON_OBJECT(" . implode(', ', $selectColumns) . ") ELSE NULLIF(1,1) END";

        return new Expr\Select($sql);
    }

    public function getSelectPart2()
    {
        return '';
    }
}
