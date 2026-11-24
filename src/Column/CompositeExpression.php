<?php

namespace Popov\DatagridBundle\Column;

use InvalidArgumentException;
use Laminas\Db\Sql\Expression;

/**
 * Keep nested Laminas parameters while exposing flat text to the DQL renderer.
 *
 * @todo Move expression composition to zfc-datagrid.
 */
final class CompositeExpression extends Expression
{
    private string $flatExpression;

    /** Preserve child expressions as parameters for Laminas SQL rendering. */
    public function __construct(string $template, array $parts)
    {
        $segments = explode('?', $template);
        if (count($segments) !== count($parts) + 1) {
            throw new InvalidArgumentException('The expression template must have one placeholder per part.');
        }

        parent::__construct($template, $parts);

        $flatExpression = array_shift($segments);
        foreach ($parts as $index => $part) {
            $flatExpression .= $part->getExpression() . $segments[$index];
        }

        $this->flatExpression = $flatExpression;
    }

    /** Expose the nested text to renderers that read only the expression string. */
    public function getExpression()
    {
        return $this->flatExpression;
    }
}
