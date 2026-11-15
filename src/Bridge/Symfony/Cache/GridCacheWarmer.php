<?php

namespace Popov\DatagridBundle\Bridge\Symfony\Cache;

use Popov\DatagridBundle\Discovery\GridRegistry;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class GridCacheWarmer implements CacheWarmerInterface
{
    private $registry;

    public function __construct(GridRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function warmUp($cacheDir, ?string $buildDir = null): array
    {
        $this->registry->rebuild($cacheDir);

        return [];
    }

    public function isOptional(): bool
    {
        return false;
    }
}
