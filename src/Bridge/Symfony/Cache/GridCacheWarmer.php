<?php

namespace Popov\DatagridBundle\Bridge\Symfony\Cache;

use Popov\DatagridBundle\Discovery\GridDiscovery;
use Popov\DatagridBundle\Discovery\GridManifest;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class GridCacheWarmer implements CacheWarmerInterface
{
    private $discovery;

    public function __construct(GridDiscovery $discovery)
    {
        $this->discovery = $discovery;
    }

    public function warmUp($cacheDir)
    {
        GridManifest::dump(
            $this->discovery->discover(),
            $cacheDir . '/popov_datagrid/grids.php'
        );

        return [];
    }

    public function isOptional()
    {
        return false;
    }
}
