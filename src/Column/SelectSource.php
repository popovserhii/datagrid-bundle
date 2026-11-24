<?php

namespace Popov\DatagridBundle\Column;

use InvalidArgumentException;
use Laminas\Db\Sql\Expression;
use ZfcDatagrid\Column\Select;

/**
 * Resolve the source of a Select without depending on a specific nested column.
 *
 * @todo Move Select source resolution to zfc-datagrid.
 */
final class SelectSource
{
    /** Keep a Select's field identity separate from the SQL source used to read it. */
    public static function resolve(Select $column): Expression
    {
        $source = $column->getSelectPart1();
        if ($source instanceof Expression) {
            return $source;
        }

        if (!is_string($source)) {
            throw new InvalidArgumentException('The Select source must be a string or a Laminas expression.');
        }

        $field = $column->getSelectPart2();
        if ($field !== null && $field !== '') {
            $source .= '.' . $field;
        }

        return new Expression($source);
    }
}
