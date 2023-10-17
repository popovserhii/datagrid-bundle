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
use Popov\DatagridBundle\Translator\TranslatorFactory;
use Popov\DatagridBundle\Factory\InvokableFactory;
use Popov\DatagridBundle\Factory\DatagridFactory;
use Popov\DatagridBundle\Router\RouterFactory;
use ZfcDatagrid\Middleware\RequestHelper;
use ZfcDatagrid\Router\RouterInterface;
use ZfcDatagrid\Translator\TranslatorInterface;

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
            ->factory(ref($factory))
            ->args([ref('service_container'), $service]);
    }

    foreach ($config['dependencies']['aliases'] as $alias => $service) {
        $services->alias($alias, $service);
    }

    unset($config['dependencies']);

    // DatagridBundle configuration
    $services->set(DatagridFactory::class);
    $services->set(RequestHelperFactory::class);
    $services->set(RouterFactory::class);
    $services->set(TranslatorFactory::class);

    $services->set(Datagrid::class)
        ->factory(ref(DatagridFactory::class))
        ->args([ref('service_container')]);

    $services->set(RequestHelper::class)
        ->factory(ref(RequestHelperFactory::class))
        ->args([ref('service_container')]);

    $services->set(RouterInterface::class)
        ->factory(ref(RouterFactory::class))
        ->args([ref('service_container')]);

    $services->set(TranslatorInterface::class)
        ->factory(ref(TranslatorFactory::class))
        ->args([ref('service_container')]);

    $services->set(TranslatorInterface::class)
        ->factory(ref(TranslatorFactory::class))
        ->args([ref('service_container')]);

    $services->set(Renderer::class)
        ->factory(ref(InvokableFactory::class))
        ->args([ref('service_container'), Renderer::class]);

    $services->alias('zfcDatagrid.renderer.jqGrid', Renderer::class);
};
