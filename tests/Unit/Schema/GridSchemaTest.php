<?php

namespace Popov\DatagridBundle\Tests\Unit\Schema;

use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Popov\DatagridBundle\Schema\GridSchema;
use RuntimeException;
use Laminas\Db\Sql\Expression;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Popov\DatagridBundle\Column\ObjectColumn;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\Datagrid;

final class GridSchemaTest extends MockeryTestCase
{
    public function testExportsMinimalGridSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->setId('user');
        $datagrid->setTitle('Users');
        $datagrid->setDefaultItemsPerPage(30);
        $datagrid->setUserFilterDisabled(true);

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        // Setup can use the API dependency.
        // Expected result must not be computed by the same dependency.
        self::assertSame([
            'schema_version' => '1.0',
            'grid' => [
                'id' => 'user',
                'title' => 'Users',
                'default_items_per_page' => 30,
                'filterable' => false,
                'parameters' => [],
                'row_styles' => [],
                'row_click_action' => null,
                'has_mass_actions' => false,
            ],
            'columns' => [],
        ], $schema);
    }

    public function testExportsBasicColumnSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn(new Select('id', 'user'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'label' => '',
            'type' => 'string',
            'position' => 1,
            'hidden' => false,
            'skipped' => false,
            'identity' => false,
            'sortable' => true,
            'translation_enabled' => false,
            'styles' => [],
            'formatters' => [],
            'actions' => [],
            'renderer_parameters' => [],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsColumnWithoutSortingConfiguration()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn(new Select('id', 'user'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => 'string',
            'sort' => [
                'default' => null,
                'active_direction' => null,
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsColumnWithConfiguredSort()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('id', 'user'))
            ->setSortDefault(1, 'DESC')
            ->setSortActive('ASC')
        );

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => 'string',
            'sort' => [
                'default' => [
                    'priority' => 1,
                    'sortDirection' => 'DESC'
                ],
                'active_direction' => 'ASC',
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsColumnWithoutFilteringConfiguration()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn(new Select('id', 'user'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => 'string',
            'filter' => [
                'enabled' => true,
                'default_value' => null,
                'default_operator' => '~ *%s*',
                'select_options' => null,
                'active_value' => null,
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsColumnWithConfiguredFilter()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('id', 'user'))
            ->setUserFilterDisabled(false)
            ->setFilterDefaultValue('lorem')
            ->setFilterDefaultOperation('= %s')
            ->setFilterSelectOptions(['lorem', 'ipsum', 'dolor'], false)
            ->setFilterActive('ipsum')
        );

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => 'string',
            'filter' => [
                'enabled' => true,
                'default_value' => 'lorem',
                'default_operator' => '= %s',
                'select_options' => ['lorem', 'ipsum', 'dolor'],
                'active_value' => 'ipsum',
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function assertArrayContains(array $expected, array $actual)
    {
        self::assertSame($expected, array_intersect_key($actual, $expected));
    }
}
