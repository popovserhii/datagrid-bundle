<?php

namespace Popov\DatagridBundle\Discovery;

use Composer\InstalledVersions;
use Psr\Container\ContainerInterface;

/**
 * Determine the current runtime environment: production or development.
 */
class Runtime
{
    /**
     * @var ContainerInterface 
     */
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;        
    }

    public function isProd()
    {
        $rootPackage = InstalledVersions::getRootPackage();

        // Production mode active (Composer installed with --no-dev).
        return isset($rootPackage['dev']) && $rootPackage['dev'] === false;
    }

    public function isDev()
    {
        return !$this->isProd();
    }

    public function isSymfony()
    {
        // Symfony
        return ($this->container->has('kernel'));
    }

    public function isLaminas()
    {
        return ($this->container->has('ApplicationConfig'));
    }
    
    public function getCacheDir()
    {
        // Symfony
        if ($this->isSymfony()) {
            $kernel = $this->container->get('kernel');

            if (method_exists($kernel, 'getCacheDir')) {
                return rtrim($kernel->getCacheDir(), DIRECTORY_SEPARATOR);
            }
        }

        // Laminas MVC
        if ($this->isLaminas()) {
            $config = $this->container->get('ApplicationConfig');

            if (isset($config['module_listener_options']['cache_dir'])) {
                return rtrim($config['module_listener_options']['cache_dir'], DIRECTORY_SEPARATOR);
            }
        }

        return '/tmp' ;
    }
}
