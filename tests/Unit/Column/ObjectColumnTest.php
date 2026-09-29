<?php

namespace Popov\DatagridBundle\Tests\Unit\Column;

use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use RuntimeException;
use Laminas\Db\Sql\Expression;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Popov\DatagridBundle\Column\ObjectColumn;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Select;

final class ObjectColumnTest extends MockeryTestCase
{
    public function testSelectIsBuiltFromConfiguredColumns()
    {
        $subject = new ObjectColumn('object', 'parent');

        $subject->addColumn(new Select('id', 'customer'));
        $subject->addColumn(new Select('title', 'customer'));

        $result = $subject->getSelectPart1();

        self::assertInstanceOf(Expression::class, $result);

        $expectedSql = "
            CASE
                WHEN customer.id IS NOT NULL
                THEN JSON_OBJECT(
                    'id', customer.id,
                    'title', customer.title
                )
                ELSE NULLIF(1,1)
            END
        ";

        // Formatter normalises SQL to avoid problem with different formats (spaces, tabs, new lines, etc.)
        $formatter = new SqlFormatter(new NullHighlighter());

        self::assertSame(
            $formatter->format($expectedSql),
            $formatter->format($result->getExpression())
        );
    }

    public function testSelectIsBuildFromConfiguredSubColumns()
    {
        $subject = new ObjectColumn('child', 'parent');
        $subject->addColumn(new Select('id', 'child'));
        $subject->addColumn(new Select('name', 'child'));

        $subSubject = new ObjectColumn('sub', 'child');
        $subSubject->addColumn(new Select('id', 'sub'));
        $subSubject->addColumn(new Select('name', 'sub'));

        $subject->addColumn($subSubject);

        $result = $subject->getSelectPart1();

        $expectedSql = "
            CASE WHEN child.id IS NOT NULL
                THEN JSON_OBJECT(
                    'id', child.id,
                    'name', child.name,
                    'sub', CASE WHEN sub.id IS NOT NULL 
                        THEN JSON_OBJECT(
                            'id', sub.id, 
                            'name', sub.name
                        ) 
                        ELSE NULLIF(1,1) END
                )
                ELSE NULLIF(1,1) END
        ";

        $actualSql = $result->getExpression();

        // Formatter normalises SQL to avoid problem with different formats (spaces, tabs, new lines, etc.)
        $formatter = new SqlFormatter(new NullHighlighter());

        self::assertSame(
            $formatter->format($expectedSql),
            $formatter->format($actualSql)
        );
    }

    public function testColumnFieldIsBuiltFromSelectPart1AndSelectPart2()
    {
        $column = Mockery::mock(Select::class);

        $column->allows()->getPosition()->andReturn(0);
        $column->allows()->getUniqueId()->andReturn('customer_name');
        $column->allows()->getSelectPart1()->andReturn('customer');
        $column->allows()->getSelectPart2()->andReturn('name');

        $subject = new ObjectColumn('object', 'parent');
        $subject->addColumn($column);

        $expression = $subject->getSelectPart1();

        $this->assertStringContainsString('customer.name', $expression->getExpression());
    }

    public function testSelectIsBuildWhenFirstColumnUniqueIdContainsEntityNameAndFieldName()
    {
        $subject = new ObjectColumn('object', 'parent');
        $subject->addColumn(new Select('id', 'customer'));

        $actualSql = $subject->getSelectPart1()->getExpression();
        $expectedSql = 'CASE WHEN customer.id IS NOT NULL';

        $formatter = new SqlFormatter(new NullHighlighter());

        $this->assertStringStartsWith(
            $formatter->format($expectedSql),
            $formatter->format($actualSql)
        );
    }

    /**
     * @dataProvider invalidFirstColumnUniqueIdProvider
     */
    public function testExceptionIsThrownWhenFirstColumnUniqueIdHasInvalidFormat($entityName, $fieldName = null)
    {
        $subject = new ObjectColumn('object', 'parent');
        $subject->addColumn(new Select($entityName, $fieldName));

        $this->expectException(RuntimeException::class);

        $subject->getSelectPart1();
    }

    public static function invalidFirstColumnUniqueIdProvider(): array
    {
        return [
            'without separator' => ['entityName', ''],
            'without entity name' => ['', '_fieldName'],
            'without field name' => ['entityName_', ''],
            'empty' => [''],
            'separator only' => ['_'],
        ];
    }
}
