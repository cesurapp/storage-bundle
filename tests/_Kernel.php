<?php

namespace Cesurapp\StorageBundle\Tests;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class _Kernel extends Kernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    public function registerBundles(): iterable
    {
        $bundles = [];

        if ('test' === $this->getEnvironment()) {
            $bundles[] = new FrameworkBundle();
        }

        return $bundles;
    }

    protected function buildContainer(): ContainerBuilder
    {
        $container = parent::buildContainer();
        $container->prependExtensionConfig('framework', ['test' => true]);

        return $container;
    }
}
