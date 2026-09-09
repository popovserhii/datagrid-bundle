<?php

namespace Popov\DatagridBundle\Discovery;

class GridRegistry
{
    /**
     * @var GridDiscovery
     */
    protected $gridDiscovery;

    /**
     * @var Runtime
     */
    protected $runtime;

    protected $manifestFile = 'popov_datagrid/grids.php';

    public function __construct(GridDiscovery $discovery, Runtime $runtimeMode)
    {
        $this->gridDiscovery = $discovery;
        $this->runtime = $runtimeMode;
    }

    public function all()
    {
        $manifest = $this->getCacheFile();
        if (file_exists($manifest)) {
            // Cache
            return GridManifest::load($manifest);
        }

        $grids = $this->gridDiscovery->discover();

        GridManifest::dump($grids, $manifest);

        return $grids;
    }

    protected function getCacheFile()
    {
        return $this->runtime->getCacheDir() . '/' . $this->manifestFile;
    }
}
