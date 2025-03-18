<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2021 Mediapark
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category ZfcDatagrid
 * @author Serhii Popov <serhii.popov@mediapark.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Factory;

use Psr\Container\ContainerInterface;
use ZfcDatagrid;

class DatagridFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return (new ZfcDatagrid\Service\DatagridFactory)($container, ZfcDatagrid\Datagrid::class);
    }
}
