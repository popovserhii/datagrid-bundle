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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Popov\DatagridBundle\Renderer\Json\Renderer;
use ZfcDatagrid\Datagrid;
use ZfcDatagrid\ConfigProvider;
use Laminas\ServiceManager\Factory\InvokableFactory as LaminasInvokableFactory;
use Popov\DatagridBundle\Factory\RequestHelperFactory;
use Popov\DatagridBundle\Factory\SessionHelperFactory;
use Popov\DatagridBundle\Translator\TranslatorFactory;
use Popov\DatagridBundle\Factory\InvokableFactory;
use Popov\DatagridBundle\Factory\DatagridFactory;
use Popov\DatagridBundle\Router\RouterFactory;
use ZfcDatagrid\Middleware\RequestHelper;
use ZfcDatagrid\Middleware\SessionHelper;
use ZfcDatagrid\Router\RouterInterface;
use ZfcDatagrid\Translator\TranslatorInterface;
use Laminas\Serializer\Adapter\PhpSerialize;
use Laminas\Cache\Storage\Adapter\Filesystem;
use Laminas\Serializer\GenericSerializerFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Cache\Storage\AdapterPluginManager;

return static function (ContainerConfigurator $configurator) {

    $services = $configurator->services()
        ->defaults()
            ->public()
            ->autowire()
            ->autoconfigure();

    // Convert Laminas configuration to Symfony
    $config = (new ConfigProvider)();

    $config['ZfcDatagrid']['settings']['default']['renderer']['http'] = 'jqGrid';

    $config['ZfcDatagrid']['renderer']['jqGrid']['parameterNames']['currentPage'] = 'page';
    $config['ZfcDatagrid']['renderer']['jqGrid']['parameterNames']['itemsPerPage'] = 'itemsPerPage';
    $config['ZfcDatagrid']['renderer']['jqGrid']['parameterNames']['sortColumns'] = 'sortBy';
    $config['ZfcDatagrid']['renderer']['jqGrid']['parameterNames']['sortDirections'] = 'sortOrder';
    $config['ZfcDatagrid']['renderer']['jqGrid']['parameterNames']['groupColumns'] = 'groupBy';

    $configurator->parameters()
        ->set('config', $config);

    foreach ($config['dependencies']['factories'] as $service => $factory) {
        if (LaminasInvokableFactory::class === $factory) {
            $factory = InvokableFactory::class;
        }

        $services->set($factory);
        $services->set($service)
            ->factory(service($factory))
            ->args([service('service_container'), $service]);
    }

    foreach ($config['dependencies']['aliases'] as $alias => $service) {
        $services->alias($alias, $service);
    }

    unset($config['dependencies']);

    // AdapterPluginManager configuration for cache
    $services->set(AdapterPluginManager::class)
        ->args([
            service('service_container'),
            [
                'factories' => [
                    PhpSerialize::class => service(GenericSerializerFactory::class),
                ],
            ],
        ])
        ->set(GenericSerializerFactory::class)
            ->args([PhpSerialize::class])
        ->set(Serializer::class)
            ->args([service(AdapterPluginManager::class)])
        ->set(Filesystem::class)
            ->args([[
                'cache_dir' => '%kernel.cache_dir%/zfc_',
                'namespace' => 'datagrid_',
                'plugins' => [
                    'exception_handler' => [
                        'throw_exceptions' => false,
                    ],
                    'Serializer',
                ],
            ]])
            //->call('addPlugin', [service(Serializer::class)])
            ;
    
    // DatagridBundle configuration
    $services->set(DatagridFactory::class);
    $services->set(RequestHelperFactory::class);
    $services->set(SessionHelperFactory::class);
    $services->set(RouterFactory::class);
    $services->set(TranslatorFactory::class);
    
    $services->set(Datagrid::class)
        ->share(false)
        ->factory(service(DatagridFactory::class))
        ->args([service('service_container')]);

    $services->set(RequestHelper::class)
        ->factory(service(RequestHelperFactory::class))
        ->args([service('service_container')]);

    $services->set(SessionHelper::class)
        ->factory(service(SessionHelperFactory::class))
        ->args([service('service_container')])
        ->lazy();

    $services->set(RouterInterface::class)
        ->factory(service(RouterFactory::class))
        ->args([service('service_container')]);
    
    $services->set(TranslatorInterface::class)
        ->factory(service(TranslatorFactory::class))
        ->args([service('service_container')]);

    $services->set(Renderer::class)
        ->share(false)
        ->factory(service(InvokableFactory::class))
        ->args([service('service_container'), Renderer::class]);

    $services->alias('zfcDatagrid.renderer.jqGrid', Renderer::class);
};
