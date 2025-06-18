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

use Psr\Container\ContainerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use ZfcDatagrid\Middleware\RequestHelper;

class RequestHelperFactory
{
    public function __invoke(ContainerInterface $container) : RequestHelper
    {
        $symfonyRequest = $container->get('request_stack')->getCurrentRequest()
            ? $container->get('request_stack')->getCurrentRequest()
            : Request::createFromGlobals();;

        $psr17Factory = new Psr17Factory();

        $psr7Request = (new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory))
            ->createRequest($symfonyRequest);

        return new RequestHelper($psr7Request);
    }
}
