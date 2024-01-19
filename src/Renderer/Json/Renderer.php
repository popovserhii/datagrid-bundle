<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2021 DatagridBundle
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Datagrid
 * @author Serhii Popov <serhii.popov@mediapark.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Renderer\Json;

use ZfcDatagrid\Filter;
use ZfcDatagrid\FilterGroup;
use ZfcDatagrid\Renderer\JqGrid\Renderer as JqGridRenderer;
use ZfcDatagrid\Column;

class Renderer extends JqGridRenderer
{
    /**
     * Available filters.
     *
     * Read more here @link http://www.trirand.com/jqgridwiki/doku.php?id=wiki:search_config#colmodel_options
     */
    // 'equal'
    const FILTER_EQ = 'eq';
    // 'not equal'
    const FILTER_NE = 'ne';
    // 'less'
    const FILTER_LT = 'lt';
    // 'less or equal'
    const FILTER_LE = 'le';
    // 'greater'
    const FILTER_GT = 'gt';
    // 'greater or equal'
    const FILTER_GE = 'ge';
    // 'begins with'
    const FILTER_BW = 'bw';
    // 'does not begin with'
    const FILTER_BN = 'nb';
    // 'is in'
    const FILTER_IN = 'in';
    // 'is not in'
    const FILTER_NI = 'ni';
    // 'ends with'
    const FILTER_EW = 'ew';
    // 'does not end with'
    const FILTER_EN = 'en';
    // 'contains'
    const FILTER_CN = 'cn';
    // 'does not contain'
    const FILTER_NC = 'nc';
    // 'between' (custom one)
    const FILTER_BT = 'bt';
    // 'NULL' (custom one)
    const FILTER_NU = 'nu';
    // 'is not NULL' (custom one)
    const FILTER_NN = 'nn';

    /**
     * List of all available operations
     *
     * @var string[]
     */
    const MATCHING_OPERATORS = [
        self::FILTER_CN => Filter::LIKE,
        self::FILTER_BW => Filter::LIKE_RIGHT,
        self::FILTER_EW => Filter::LIKE_LEFT,
        self::FILTER_NC => Filter::NOT_LIKE,
        self::FILTER_BN => Filter::NOT_LIKE_LEFT,
        self::FILTER_EN => Filter::NOT_LIKE_RIGHT,
        self::FILTER_EQ => Filter::EQUAL,
        self::FILTER_NE => Filter::NOT_EQUAL,
        self::FILTER_GE => Filter::GREATER_EQUAL,
        self::FILTER_GT => Filter::GREATER,
        self::FILTER_LE => Filter::LESS_EQUAL,
        self::FILTER_LT => Filter::LESS,
        self::FILTER_IN => Filter::IN,
        self::FILTER_NI => Filter::NOT_IN,
        self::FILTER_BT => Filter::BETWEEN,
        self::FILTER_NU => Filter::NULL,
        self::FILTER_NN => Filter::NOT_NULL,
    ];

    /**
     * @return string
     */
    /*public function getName(): string
    {
        return 'json';
    }*/

//    public function getGroupColumns()
//    {
//        $orderColumns = explode(',', $this->getRequestParam('groupColumns'));
//        if ($orderColumns) {
//            foreach ($orderColumns as & $orderColumn) {
//                $orderColumn = str_replace('.', '_', $orderColumn);
//            }
//            $orderColumns = implode(',', $orderColumns);
//        }
//        return $orderColumns;
//    }

//    public function getSortColumns()
//    {
//        $sortColumns = explode(',', $this->getRequestParam('sortColumns'));
//        if ($sortColumns) {
//            foreach ($sortColumns as & $sortColumn) {
//                $sortColumn = str_replace('.', '_', $sortColumn);
//            }
//            $sortColumns = implode(',', $sortColumns);
//        }
//        return $sortColumns;
//    }

    /**
     * @throws \Exception
     */
    public function getFilters()
    {
        if (!empty($this->filters)) {
            // set from cache! (for export)
            return $this->filters;
        }

        $optionsRenderer = $this->getOptionsRenderer();
        $parameterNames  = $optionsRenderer['parameterNames'];

        $request  = $this->getRequest();
        $postParams = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        $filters = [];

        // User filtering
        foreach ($this->getColumns() as $column) {
            $uniqueId = $column->getUniqueId();
            #$fieldName = $column->getSelectPart1() . '.' . $column->getSelectPart2();

            $value = $postParams[$uniqueId] ?? $queryParams[$uniqueId] ?? null;
            #$value = $request->getPost($column->getUniqueId(), $request->getQuery($column->getUniqueId()));
            /* @var $column \ZfcDatagrid\Column\AbstractColumn */
            if ($value != '') {
                // @todo: Convert it ot FilterGroup
                $filters[] = $this->createFilter($column, $value);
            } /*elseif ($filterGroup) {
                $simpleFilter = implode(',', $extendedFilters[$uniqueId]['values']);
                $filter = $this->createFilter($column, $simpleFilter);
                $filters[] = $filter;
            }*/
        }

        $values = $postParams['filters'] ?? $queryParams['filters'] ?? null;
        $filterGroup = $this->prepareFilters(json_decode($values, true));

        //if (empty($filters)) {
        if (!$filterGroup) {
            // No user sorting -> get default sorting
            $filterGroup = $this->getFiltersDefault();
        }

        //$this->filters = $filters;
        $this->filters = $filterGroup;

        return $this->filters;
    }

//    public function createFilter($column, $value)
//    {
//        /* @var $column \ZfcDatagrid\Column\AbstractColumn */
//        $filter = new \ZfcDatagrid\Filter();
//        $filter->setFromColumn($column, $value);
//        $column->setFilterActive($filter->getDisplayColumnValue());
//
//        return $filter;
//    }

//    public function createColumn($columnName, $tableAlias)
//    {
//        return (new Column\Select($columnName, $tableAlias))->setSkipped();
//    }

    public function prepareFilters($rawFilters)
    {
        if (!$rawFilters) {
            return null;
        }

        $filterGroup = new FilterGroup();
        foreach ($rawFilters as $key => $value) {
            // @todo: Create library's exception and throw Exception if $value is empty
            if ($value && $key === 'groupOp') {
                $filterGroup->setCondition($value);
            } elseif ($value && $key === 'rules') {
                foreach ($value as $rule) {
                    //$uniqueId = str_replace('.', '_', $rule['field']);
                    //[$tableAlias, $columnName] = explode('.', $rule['field']);
                    //$column = $this->getColumn($uniqueId) ?: $this->createColumn($columnName, $tableAlias);

                    $column = $this->getColumnByName($rule['field']);
                    $column->setFilterDefaultOperation(self::MATCHING_OPERATORS[$rule['op']]);

                    $filter = $this->createFilter($column, $rule['data']);
                    if ($this->isFilterIgnored($filter)) {
                        continue;
                    }
                    $filterGroup->addFilter($filter);
                }
            } elseif ($value && $key === 'groups') {
                //foreach ($value as $sub) {
                    $filterGroup->addGroup($this->prepareFilters($value));
                //}
            }
        }

        return $filterGroup;
    }

    public function execute()
    {
        return  $this->getJsonData();
    }

    public function getData(): array
    {
        $gridId = '';
        $data = $this->data;
        foreach ($data as $key => & $row) {
            $index = 0;
            $entities = [];
            foreach ($this->getColumns() as $column) {
                if ($column->isSkipped()) {
                    unset($row[$column->getUniqueId()]);
                    continue;
                }
                if ($column instanceof Column\Select) {
                    $entityName = strtok($column->getUniqueId(), '_');
                    $fieldName = substr($column->getUniqueId(), strlen($entityName) + 1);

                    $entities[$entityName] = $entityName; // Life hack to optimize the memory

                    // Determine base grid ID to build appropriate JSON hierarchy
                    if (0 === $index && !$gridId) {
                        $gridId = $entityName;
                    }

                    $value = $row[$column->getUniqueId()];
                    unset($row[$column->getUniqueId()]);

                    if ($gridId === $entityName) {
                        $row[$fieldName] = $value;
                    } elseif ($fieldName) {
                        $row[$entityName][$fieldName] = $value;
                    } else {
                        $row[$entityName] = $value;
                    }
                }
                $index++;
            }

            // Convert an entity with all empty fields to empty array to have a correct structure in a response.
            unset($entities[$gridId]);
            foreach ($entities as $entityName) {
                $values = array_filter($row[$entityName]);
                if (!$values) {
                    $row[$entityName] = [];
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    private function getJsonData(): array
    {
        return [
            'rows'    => $this->getData(),
            'page'    => $this->getPaginator()->getCurrentPageNumber(),
            'total'   => $this->getPaginator()->count(),
            'records' => $this->getPaginator()->getTotalItemCount(),
        ];
    }
}
