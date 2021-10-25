<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2021 DatagridBundle
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category DatagridBundle
 * @author Serhii Popov <popow.serhii@mediapark.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */


namespace Popov\DatagridBundle\Router;

use ZfcDatagrid\Router\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SymfonyRouter implements RouterInterface
{
    protected $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function assemble(array $params = [], array $options = [])
    {
        #$this->router->generate($options['name'], $params, UrlGeneratorInterface::ABSOLUTE_URL);
        $this->router->generate($options['name'], $params);
    }
}
