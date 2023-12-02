<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\StorageBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Create App Test Kernel.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new StorageBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
        ]);

        // Storage Bundle Default Configuration
        $container->extension('storage', [
            'default' => 'main',
            'devices' => [
                'main' => [
                    'driver' => 'local',
                    'root' => '%kernel.project_dir%/var',
                ],
            ],
        ]);
    }

    /*protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller([$this, 'helloAction']);
    }*/
}
