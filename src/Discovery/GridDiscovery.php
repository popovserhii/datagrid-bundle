<?php

namespace Popov\DatagridBundle\Discovery;

use Generator;
use ReflectionClass;
use RuntimeException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use Popov\DatagridBundle\GridInterface;
use Composer\Autoload\ClassLoader;

final class GridDiscovery
{
    /**
     * @return string[]
     */
    public function discover()
    {
        $grids = [];

        foreach ($this->getComposerLoaders() as $loader) {
            $grids = array_merge(
                $this->discoverClassMap($loader),
                $this->discoverPsr4($loader)
            );
        }

        $grids = array_unique($grids);

        sort($grids);
        
        return $grids;
    }

    protected function getComposerLoaders()
    {
        $loaders = [];

        $autoloads = spl_autoload_functions() ?: [];
        foreach ($autoloads as $autoload) {
            if (is_array($autoload)
                && isset($autoload[0])
                && $autoload[0] instanceof ClassLoader
            ) {
                $loader = $autoload[0];
                $loaders[spl_object_hash($loader)] = $loader;
            }
        }

        if (!$loaders) {
            throw new RuntimeException('Could not determine loader of Composer autoload');
        }

        return $loaders;
    }

    /**
     * Composer already knows these classes, so there is no reason to scan their files.
     *
     * @param ClassLoader $loader
     *
     * @return int[]|string[]
     */
    protected function discoverClassMap(ClassLoader $loader)
    {
        $grids = [];
        foreach ($loader->getClassMap() as $class => $file) {
            $file = $this->normalizePath($file);
            #$classMapFiles[$file] = true;

            if (!$this->isGridFile($file)) {
                continue;
            }

            if ($this->isGridClass($class)) {
                $grids[$class] = true;
            }
        }

        return array_keys($grids);
    }
    
    protected function discoverPsr4(ClassLoader $loader)
    {
        $grids = [];
        foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
            foreach ($directories as $directory) {
                foreach ($this->findGridFiles($directory) as $file) {
                    $class = $this->getPsr4Class(
                        $prefix,
                        $directory,
                        $file
                    );

                    if ($this->isGridClass($class)) {
                        $grids[$class] = true;
                    }
                }
            }
        }

        return array_keys($grids);
    }

    protected function getPsr4Class($prefix, $directory, $file)
    {
        $directory = rtrim(
            str_replace('\\', '/', realpath($directory)),
            '/'
        );

        $file = str_replace('\\', '/', realpath($file));

        $relative = substr(
            $file,
            strlen($directory) + 1
        );

        // Grid/ProjectGrid.php
        $relative = substr($relative, 0, -4);

        // Grid\ProjectGrid
        $relative = str_replace('/', '\\', $relative);

        return $prefix . $relative;
    }

    /**
     * @return Generator|string[]
     */
    protected function findGridFiles($directory)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!$this->isGridFile($file->getFilename())) {
                continue;
            }

            yield $file->getPathname();
        }
    }

    protected function isGridFile($file)
    {
        return substr($file, -8) === 'Grid.php';
    }

    protected function isGridClass($class)
    {
        if (!is_subclass_of($class, GridInterface::class, true)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return !$reflection->isAbstract()
            && !$reflection->isInterface()
            && !$reflection->isTrait();
    }

    protected function normalizePath($path)
    {
        $realPath = realpath($path);

        return $realPath !== false
            ? $realPath
            : $path;
    }
}
