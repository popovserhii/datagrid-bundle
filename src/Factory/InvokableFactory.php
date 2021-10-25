<?php

declare(strict_types=1);

namespace Popov\DatagridBundle\Factory;

use Psr\Container\ContainerInterface;

/**
 * NOTE: It's copy-pasted factory from Laminas project
 * for compatibility with PSR standard that cannot be implemented yet
 * due to this issue @xee https://github.com/laminas/laminas-servicemanager/issues/92
 *
 * Factory for instantiating classes with no dependencies or which accept a single array.
 *
 * The InvokableFactory can be used for any class that:
 *
 * - has no constructor arguments;
 * - accepts a single array of arguments via the constructor.
 *
 * It replaces the "invokables" and "invokable class" functionality of the v2
 * service manager.
 */
final class InvokableFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return null === $options ? new $requestedName() : new $requestedName($options);
    }
}
