<?php

namespace Popov\DatagridBundle\Tests\Unit\Discovery;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Popov\DatagridBundle\Discovery\GridDiscovery;
use Popov\DatagridBundle\GridInterface;
use RuntimeException;
use Symfony\Component\Debug\DebugClassLoader as LegacyDebugClassLoader;
use Symfony\Component\ErrorHandler\DebugClassLoader;

final class GridDiscoveryTest extends TestCase
{
    /**
     * @dataProvider loaderWrappers
     * @group legacy
     */
    public function testDiscoversGridsWithDirectOrWrappedComposerLoader(array $wrappers): void
    {
        $loader = new ClassLoader();
        $loader->addClassMap([FirstGrid::class => __DIR__ . '/FirstGrid.php']);
        $autoload = [$loader, 'loadClass'];

        foreach ($wrappers as $wrapper) {
            if (!class_exists($wrapper)) {
                self::markTestSkipped($wrapper . ' is not installed.');
            }

            $autoload = [new $wrapper($autoload), 'loadClass'];
        }

        self::assertSame([FirstGrid::class], $this->discoverWithAutoloaders([$autoload]));
    }

    public function loaderWrappers(): array
    {
        return [
            'direct Composer loader' => [[]],
            'Symfony Debug component' => [[LegacyDebugClassLoader::class]],
            'Symfony ErrorHandler component' => [[DebugClassLoader::class]],
            'nested Symfony wrappers' => [[LegacyDebugClassLoader::class, DebugClassLoader::class]],
        ];
    }

    public function testKeepsGridsFromAllComposerLoadersSortedAndUnique(): void
    {
        $first = new ClassLoader();
        $first->addClassMap([
            SecondGrid::class => __DIR__ . '/SecondGrid.php',
            FirstGrid::class => __DIR__ . '/FirstGrid.php',
        ]);
        $second = new ClassLoader();
        $second->addClassMap([SecondGrid::class => __DIR__ . '/SecondGrid.php']);

        self::assertSame([FirstGrid::class, SecondGrid::class], $this->discoverWithAutoloaders([
            [$first, 'loadClass'],
            [$second, 'loadClass'],
        ]));
    }

    public function testRejectsAutoloadersWithoutComposer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not determine loader of Composer autoload');

        $this->discoverWithAutoloaders([static function ($class) {}]);
    }

    private function discoverWithAutoloaders(array $autoloaders): array
    {
        $discovery = new GridDiscovery();
        $original = spl_autoload_functions() ?: [];

        foreach ($original as $autoload) {
            spl_autoload_unregister($autoload);
        }

        try {
            foreach ($autoloaders as $autoload) {
                spl_autoload_register($autoload);
            }

            return $discovery->discover();
        } finally {
            foreach (spl_autoload_functions() ?: [] as $autoload) {
                spl_autoload_unregister($autoload);
            }

            foreach ($original as $autoload) {
                spl_autoload_register($autoload);
            }
        }
    }
}

final class FirstGrid implements GridInterface
{
    public function init()
    {
    }
}

final class SecondGrid implements GridInterface
{
    public function init()
    {
    }
}
