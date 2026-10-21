<?php

namespace Popov\DatagridBundle\Tests\Unit\Schema;

use IntlDateFormatter;
use NumberFormatter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Popov\DatagridBundle\Column\CollectionColumn;
use Popov\DatagridBundle\Schema\GridSchema;
use Popov\DatagridBundle\Type\JsonCollection;
use Popov\DatagridBundle\Type\JsonObject;
use RuntimeException;
use Laminas\Db\Sql\Expression;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Popov\DatagridBundle\Column\ObjectColumn;
use Popov\DatagridBundle\Type\JsonableArray;
use ZfcDatagrid\Column\AbstractColumn;
use ZfcDatagrid\Column\Select;
use ZfcDatagrid\Column\Type;
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
            'type' => [
                'name' => 'string'
            ],
            'position' => 1,
            'hidden' => false,
            'skipped' => false,
            'identity' => false,
            'sortable' => true,
            'translation_enabled' => false,
            'styles' => [],
            'actions' => [],
            'renderer_parameters' => [],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsNumberTypeSchema()
    {
        $type = (new Type\Number(NumberFormatter::DECIMAL, NumberFormatter::TYPE_DEFAULT, 'uk_UA'))
            ->addAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2)
            ->addAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP)
            ->addAttribute(NumberFormatter::GROUPING_USED, 1)
            ->setPrefix('€ ')
            ->setSuffix(' EUR')
            ->setPattern('#,##0.00');

        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('price', 'work'))->setLabel('Price')->setType($type));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'work_price',
            'label' => 'Price',
            'type' => [
                'name' => 'number',
                'style' => 'decimal',
                'notation' => 'standard',
                'locale' => 'uk-UA',
                'prefix' => '€ ',
                'suffix' => ' EUR',
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsDateTimeTypeSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('createdAt', 'user'))->setLabel('Created at')->setType(
            (new Type\DateTime(
                'Y-m-d H:i:s',
                IntlDateFormatter::LONG,
                IntlDateFormatter::SHORT,
                'uk_UA',
                'UTC',
                'Europe/Kyiv'
            ))
                ->setDaterangePickerEnabled()
                ->setOutputPattern('yyyy-MM-dd HH:mm')
        ));

        $schema = (new GridSchema())->create($datagrid);

        $expected = [
            'id' => 'user_createdAt',
            'label' => 'Created at',
            'type' => [
                'name' => 'dateTime',
                'format' => 'yyyy-MM-dd HH:mm',
                'timezone' => 'Europe/Kyiv',
                'locale' => 'uk-UA',
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsImageTypeSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('avatar', 'user'))->setType(
            (new Type\Image())
                ->setResizeType('dynamic')
                ->setResizeHeight(48.5)
        ));

        $schema = (new GridSchema())->create($datagrid);

        $expected = [
            'type' => ['name' => 'image'],
        ];

        $this->assertArrayContains($expected, $schema['columns'][0]);
    }

    public function testExportsPhpArrayTypeSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('roles', 'user'))->setType(new Type\PhpArray('|')));

        $schema = (new GridSchema())->create($datagrid);

        $expected = [
            'type' => ['name' => 'array']
        ];

        self::assertSame($expected['type'], $schema['columns'][0]['type']);
    }

    public function testExportsJsonableArrayTypeSchema()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('settings', 'user'))->setType(
            (new JsonableArray())->setFilterDefaultOperation('= %s')
        ));

        $schema = (new GridSchema())->create($datagrid);

        $expected = [
            'type' => ['name' => 'array'],
        ];

        self::assertSame($expected['type'], $schema['columns'][0]['type']);
    }

    public function testExportsColumnWithoutSortingConfiguration()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn(new Select('id', 'user'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => [
                'name' => 'string',
            ],
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
            ->setSortActive('ASC'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => [
                'name' => 'string',
            ],
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
            'type' => [
                'name' => 'string',
            ],
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
            ->setFilterActive('ipsum'));

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            'id' => 'user_id',
            'type' => [
                'name' => 'string',
            ],
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

    public function testExportsObjectColumnType()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('id', 'user')));

        $objectColumn = (new ObjectColumn('customer', 'user'));
        $objectColumn->addColumn(new Select('id', 'customer'));
        $objectColumn->addColumn(new Select('title', 'customer'));

        $datagrid->addColumn($objectColumn);

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            [
                'id' => 'user_id',
                'type' => [
                    'name' => 'string',
                ]
            ],
            [
                'id' => 'user_customer',
                'type' => [
                    'name' => 'object',
                ],
                'columns' => [
                    [
                        'id' => 'customer_id',
                        'type' => [
                            'name' => 'string',
                        ]
                    ],
                    [
                        'id' => 'customer_title',
                        'type' => [
                            'name' => 'string',
                        ]
                    ],
                ]
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns']);
    }

    public function testExportsNestedColumnType()
    {
        $datagrid = new Datagrid();
        $datagrid->addColumn((new Select('id', 'user')));

        $objectColumn = (new CollectionColumn('roles', 'user'));
        $objectColumn->addColumn(new Select('id', 'role'));
        $objectColumn->addColumn(new Select('type', 'role'));

        $datagrid->addColumn($objectColumn);

        $subject = new GridSchema();
        $schema = $subject->create($datagrid);

        $expected = [
            [
                'id' => 'user_id',
                'type' => [
                    'name' => 'string',
                ]
            ],
            [
                'id' => 'user_roles',
                'type' => [
                    'name' => 'collection',
                ],
                'columns' => [
                    [
                        'id' => 'role_id',
                        'type' => [
                            'name' => 'string',
                        ]
                    ],
                    [
                        'id' => 'role_type',
                        'type' => [
                            'name' => 'string',
                        ]
                    ],
                ]
            ],
        ];

        $this->assertArrayContains($expected, $schema['columns']);
    }

    public function assertArrayContains(array $expected, array $actual): void
    {
        foreach ($expected as $key => $expectedValue) {
            self::assertArrayHasKey($key, $actual);

            $actualValue = $actual[$key];

            if (is_array($expectedValue)) {
                self::assertIsArray($actualValue);

                $this->assertArrayContains($expectedValue, $actualValue);

                continue;
            }

            self::assertSame($expectedValue, $actualValue);
        }
    }
}
