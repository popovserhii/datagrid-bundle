<?php
namespace Popov\DatagridBundle\DataSource\Elastica;

use Doctrine\ORM\Query\Expr;
use Elastica\QueryBuilder;
use ZfcDatagrid\Column;
use ZfcDatagrid\Filter as DatagridFilter;
use ZfcDatagrid\FilterGroup;
use function sprintf;
use function str_replace;

class Filter
{
    /**
     * @var QueryBuilder
     */
    private $qb;

    /**
     * @param QueryBuilder $qb
     */
    public function __construct(QueryBuilder $qb)
    {
        $this->qb = $qb;
        //$this->expr = $qb->query()->constant_score();
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->qb;
    }

    public function applyFilters($filterGroup)
    {
        if (!$filterGroup) {
            return;
        }
        
        $qb = $this->getQueryBuilder();
        if (($expr = $this->applyFilter($filterGroup))) {
            #FilterGroup::COND_AND === $filterGroup->getCondition()
            #    ? $this->expr->setParam('must', $clauses)
            #    : $this->expr->setParam('must_not', $clauses);
            #$expr = $qb->query()->constant_score($expr);

            $qb = $this->getQueryBuilder();
            $expr = $qb->query()->bool()->setParam('filter', $expr);
        }

        return $expr;
    }

    /**
     * @param FilterGroup $filterGroup
     *
     * @throws \Exception
     * @return $this
     */
    public function applyFilter(/*DatagridFilter*/ $filterGroup)
    {
        if (!($filters = $filterGroup->getFilters())) {
            return false;
        }

        $qb = $this->getQueryBuilder();

        $expr = $qb->query()->bool();

        $clauses = [];
        foreach ($filters as $i => $filter) {
            $column = $filter->getColumn();
            if (!$column instanceof Column\Select) {
                throw new \Exception('This column cannot be filtered: ' . $column->getUniqueId());
            }
            $colString = $column->getSelectPart1();
            if ($column->getSelectPart2() != '') {
                $colString .= '.' . $column->getSelectPart2();
            }
            if ($column instanceof Column\Select && $column->hasFilterSelectExpression()) {
                $colString = sprintf($column->getFilterSelectExpression(), $colString);
            }

            $values = $filter->getValues();
            foreach ($values as $key => $value) {
                #$valueParameterName = ':' . str_replace('.', '', $column->getUniqueId() . $i . $key);
                switch ($filter->getOperator()) {
                    case DatagridFilter::LIKE:
                        //$clauses[] = $expr->like($colString, $valueParameterName);
                        //$qb->setParameter($valueParameterName, '%' . $value . '%');
                        $clause = $qb->query()->match_phrase($colString, $value);

                        break;
                    case DatagridFilter::LIKE_LEFT:
                        //$clauses[] = $expr->like($colString, $valueParameterName);
                        //$qb->setParameter($valueParameterName, '%' . $value);
                        $clause = $qb->query()->prefix([$colString, $value]);

                        break;
                    case DatagridFilter::LIKE_RIGHT:
                        #$clause = $expr->like($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, $value . '%');
                        $clause = $qb->query()->wildcard($colString, $value . '*');

                        break;
                    case DatagridFilter::NOT_LIKE:
                        #$clause = $expr->notLike($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, '%' . $value . '%');
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->match_phrase($colString, $value));

                        break;
                    case DatagridFilter::NOT_LIKE_LEFT:
                        #$clause = $expr->notLike($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, '%' . $value);
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->prefix([$colString, $value]));

                        break;
                    case DatagridFilter::NOT_LIKE_RIGHT:
                        #$clause = $expr->notLike($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, $value . '%');
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->wildcard($colString, $value . '*'));

                        break;
                    case DatagridFilter::EQUAL:
                        $clause = $qb->query()->term([$colString => $value]);
                        #$expr->addFilter($qb->query()->term([$colString => $value]));

                        break;
                    case DatagridFilter::NOT_EQUAL:
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->term([$colString => $value]));
                        #$expr->addFilter($expr->addMustNot($qb->query()->term([$colString => $value])));

                        break;
                    case DatagridFilter::GREATER_EQUAL:
                        $clause = $qb->query()->range($colString, ['gte' => $value]);

                        break;
                    case DatagridFilter::GREATER:
                        $clause = $qb->query()->range($colString, ['gt' => $value]);

                        break;
                    case DatagridFilter::LESS_EQUAL:
                        $clause = $qb->query()->range($colString, ['lte' => $value]);

                        break;
                    case DatagridFilter::LESS:
                        $clause = $qb->query()->range($colString, ['lt' => $value]);

                        break;
                    case DatagridFilter::IN: // @see https://discuss.elastic.co/t/sql-in-clause-equivalent/149484
                        #$clause = $expr->in($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, $values);
                        $clause = $qb->query()->terms($colString, $values);

                        break 2;
                    case DatagridFilter::NOT_IN:
                        #$clause = $expr->notIn($colString, $valueParameterName);
                        #$qb->setParameter($valueParameterName, $values);
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->terms($colString, $values));

                        break 2;
                    case DatagridFilter::BETWEEN: // @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-range-query.html
                        // BETWEEN is a shorthand for the longer syntax
                        // that includes both values (EventDate >= '10/15/2009' and EventDate <= '10/19/2009')
                        #$minParameterName = ':' . str_replace('.', '', $colString . '0');
                        #$maxParameterName = ':' . str_replace('.', '', $colString . '1');
                        #$clause = $expr->between($colString, $minParameterName, $maxParameterName);
                        #$qb->setParameter($minParameterName, $values[0]);
                        #$qb->setParameter($maxParameterName, $values[1]);

                        $clause = $qb->query()->range($colString, [
                            'gte' => $values[0],
                            'lte' => $values[1]
                        ]);

                        break 2;
                    case DatagridFilter::NULL:
                        #$clause = $expr->isNull($colString);
                        $clause = $qb->query()->bool()->addMustNot($qb->query()->exists($colString));

                        break;
                    case DatagridFilter::NOT_NULL:
                        #$clause = $expr->isNotNull($colString);
                        $clause = $qb->query()->exists($colString);

                        break;
                    default:
                        throw new \InvalidArgumentException(
                            'This operator is currently not supported: ' . $filter->getOperator()
                        );
                }

                if ($column->getSelectPart1()) {

                }

                $clauses[] = $clause;
            }
        }

        if (!empty($clauses)) {
            if ($groups = $filterGroup->getGroups()) {
                foreach ($groups as $group) {
                    $clauses[] = $this->applyFilter($group);
                }
            }

            FilterGroup::COND_AND === $filterGroup->getCondition()
                #? $expr->addMust($clauses)
                #: $expr->addMustNot($clauses);
                ? $expr->setParam('must', $clauses)
                : $expr->setParam('should', $clauses);
        }

        return $expr;
        #return $clauses;
    }
}
