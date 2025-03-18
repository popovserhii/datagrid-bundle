<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2021 Mediapark
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Datagrid
 * @author Serhii Popov <serhii.popov@mediapark.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\DatagridBundle\Factory;

use Laminas\Serializer\Adapter\PhpSerialize;
use Laminas\Serializer\GenericSerializerFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ZfcDatagrid\Middleware\SessionHelper;

class AdapterPluginManagerFactory
{
    public function __invoke(ContainerInterface $container) : SessionHelper
    {
        $genericSerializerFactory = new GenericSerializerFactory(PhpSerialize::class);
        $container->set(PhpSerialize::class, PhpSerialize::class);
        
        $adapterPluginManager = new GenericSerializerFactory(PhpSerialize::class);

        return new GenericSerializerFactory(PhpSerialize::class;
    }
}
