<?php

namespace Popov\DatagridBundle\Discovery;

use ReflectionClass;
use InvalidArgumentException;

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

        $grids = $this->mapping($grids);

        GridManifest::dump($grids, $manifest);

        return $grids;
    }

    public function has($key)
    {
        return isset($this->all()[$key]);
    }

    public function get($key)
    {
        if (!$this->has($key)) {
            // The system cannot silently ignore incorrect call
            // because this indicates that the system is misconfigured.
            throw new InvalidArgumentException(strtr('The Grid with ID: {key} not found', ['{key}' => $key]));
        }

        return $this->all()[$key];
    }

    protected function getCacheFile()
    {
        return $this->runtime->getCacheDir() . '/' . $this->manifestFile;
    }

    protected function mapping(array $grids)
    {
        $map = [];
        foreach ($grids as $grid) {
            $reflection = new ReflectionClass($grid);
            if (!$reflection->hasConstant('GRID_ID')) {
                continue;
            }
            $id = $reflection->getConstant('GRID_ID');
            $map[$id] = $grid;
        }

        return $map;
    }
}
