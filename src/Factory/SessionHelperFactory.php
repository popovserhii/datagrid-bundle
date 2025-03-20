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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ZfcDatagrid\Middleware\SessionHelper;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionHelperFactory
{
    public function __invoke(ContainerInterface $container) : SessionHelper
    {
//        $requestStack = $container->has(RequestStack::class)
//            ? $container->get(RequestStack::class)
//            : ($container->has('@request_stack')
//                ? $container->get('@request_stack')
//                : ($container->has('request_stack')
//                    ? $container->get('request_stack')
//                    : null));
//        $session = $requestStack->getSession();
        
        $session = $container->get(SessionInterface::class);

        return new SessionHelper($session);
    }
}
