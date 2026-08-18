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
use Doctrine\ORM\Query\Expr;
use Laminas\Db\Sql\Expression;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\Datagrid;
use MediaparkLt\CustomerBundle\Entity\Customer;
use MediaparkLt\ProjectBundle\Entity\Status;

class NestedColumn extends ObjectColumn
{
    const COLUMNS_PLACEHOLDER = ':columnConcated:';

    protected $sqlTemplate = '';

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
            throw new RuntimeException('Grid ID is not defined. The grid must have at least one column with entity name as prefix (e.g. customer_id, project_id, etc.).');
        }

        $sql = "CONCAT('[', GROUP_CONCAT(DISTINCT CASE WHEN {$gridId}.{$primaryName} IS NOT NULL THEN JSON_OBJECT(" . implode(', ', $selectColumns) . ") ELSE NULLIF(1,1) END), ']')";

        $sql = $this->getSqlWrapped($sql);
        
        #return new Expr\Select($sql);
        return new Expression($sql);
    }

    public function getSelectPart2()
    {
        return '';
    }

    public function setSqlTemplate($sqlTemplate)
    {

        $this->sqlTemplate = $sqlTemplate;

        return $this;
    }

    protected function getSqlWrapped($sql)
    {
        if (!$this->sqlTemplate) {
            // There is nothing to replace. The nested SQL is prepered on the Table level.
            return $sql;
        }

        if (strpos($this->sqlTemplate, self::COLUMNS_PLACEHOLDER) === false) {
            throw new RuntimeException('The nested SQL template must contain the placeholder ' . self::COLUMNS_PLACEHOLDER);
        }
        
        // Replace placeholders
        $sqlWrapped = str_replace(self::COLUMNS_PLACEHOLDER, $sql, $this->sqlTemplate);

        return $sqlWrapped;
    }
}
