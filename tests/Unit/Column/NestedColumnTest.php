<?php

namespace Popov\DatagridBundle\Tests\Unit\Column;

use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Popov\DatagridBundle\Column\NestedColumn;
use RuntimeException;
use Laminas\Db\Sql\Expression;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Popov\DatagridBundle\Column\ObjectColumn;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Select;

final class NestedColumnTest extends MockeryTestCase
{
    public function testSelectIsBuiltFromConfiguredColumns()
    {
        $subject = new NestedColumn('client', 'customer');

        $subject->addColumn(new Select('id', 'client'));
        $subject->addColumn(new Select('title', 'client'));

        $result = $subject->getSelectPart1();

        self::assertInstanceOf(Expression::class, $result);

        $expectedSql = "
            CONCAT('[', GROUP_CONCAT(DISTINCT 
                CASE
                    WHEN client.id IS NOT NULL
                    THEN JSON_OBJECT(
                        'id', client.id,
                        'title', client.title
                    )
                    ELSE NULLIF(1,1)
                END
            ), ']')
        ";

        // Formatter normalises SQL to avoid problem with different formats (spaces, tabs, new lines, etc.)
        $formatter = new SqlFormatter(new NullHighlighter());

        self::assertSame(
            $formatter->format($expectedSql),
            $formatter->format($result->getExpression())
        );
    }
}
