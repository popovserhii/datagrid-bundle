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

use RuntimeException;
use ZfcDatagrid\Column;
use ZfcDatagrid\Column\Type;
use Popov\DatagridBundle\Type\JsonObject;
use ZfcDatagrid\Column\AbstractColumn;

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
        return $this->buildSqlJsonExpression();
    }

    public function getSelectPart2()
    {
        return '';
    }

    public function buildSqlJson()
    {
        return $this->buildSqlJsonExpression()->getExpression();
    }

    /** Build the object from child sources so the identity expression also controls nullability. */
    protected function buildSqlJsonExpression(): CompositeExpression
    {
        $this->getPrimaries();

        $primaryColumn = reset($this->columns);
        $selectColumns = [];
        $parameters = [SelectSource::resolve($primaryColumn)];

        foreach ($this->getColumns() as $column) {
            [$entityName, $fieldName] = $this->getUniqueParts($column->getUniqueId());
            $selectColumns[] = "'" . str_replace("'", "''", $fieldName) . "', ?";
            $parameters[] = SelectSource::resolve($column);
        }

        $sql = "
            CASE WHEN ? IS NOT NULL THEN
                JSON_OBJECT(" . implode(', ', $selectColumns) . ")
            ELSE NULLIF(1,1) END
        ";

        return new CompositeExpression($sql, $parameters);
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
            [$gridId, $primaryName] = $this->getUniqueParts($column->getUniqueId());

            break;
        }

        if (empty($primaryName)) {
            throw new RuntimeException('Grid ID is not defined. Your grid must have at least one column with entity name as prefix (e.g. customer_id, project_id, etc.).');
        }

        return [$gridId, $primaryName];
    }

    /**
     * @todo-serhii Reduce code duplication and create one source of truth.
     */
    protected function getUniqueParts($id)
    {
        $uniqueId = rtrim($id, '_');
        $entityName = strtok($uniqueId, '_');
        $fieldName = substr($uniqueId, strlen($entityName) + 1);

        return [$entityName, $fieldName];
    }
}
