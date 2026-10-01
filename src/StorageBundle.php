<?php

namespace Cesurapp\StorageBundle;

use Cesurapp\StorageBundle\Driver\BackBlaze;
use Cesurapp\StorageBundle\Driver\Cloudflare;
use Cesurapp\StorageBundle\Driver\Local;
use Cesurapp\StorageBundle\Storage\Storage;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StorageBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default')->isRequired()->end()
                    ->arrayNode('devices')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                        ->children()
                            ->enumNode('driver')->isRequired()->values(['local', 'cloudflare', 'backblaze'])->end()
                            ->scalarNode('root')->isRequired()->end()
                            ->scalarNode('accessKey')->defaultValue('')->end()
                            ->scalarNode('secretKey')->defaultValue('')->end()
                            ->scalarNode('bucket')->defaultValue('')->end()
                            ->scalarNode('bucketPrivate')->defaultValue('')->end()
                            ->scalarNode('region')->defaultValue('')->end()
                            ->scalarNode('endPoint')->defaultValue('')->end()
                            ->scalarNode('domain')->defaultValue('')->end()
                            ->floatNode('timeout')->defaultNull()->info('Seconds an HTTP request to the cloud storage may take, the HTTP client\'s "timeout" option')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $deviceDefinitions = [];
        foreach ($config['devices'] as $device => $value) {
            $class = match ($value['driver']) {
                'cloudflare' => Cloudflare::class,
                'backblaze' => BackBlaze::class,
                default => Local::class,
            };

            $ref = new \ReflectionClass($class);
            $constructors = array_map(static fn (\ReflectionParameter $param) => $param->name, $ref->getConstructor()->getParameters());

            // Set Service
            $definition = new Definition($class);
            $initData = array_intersect_key($value, array_flip($constructors));
            foreach ($initData as $key => $val) {
                $definition->setArgument("$$key", $val);
            }

            // Inject HTTP Client for Cloudflare and BackBlaze drivers
            if (Cloudflare::class === $class || BackBlaze::class === $class) {
                $httpClient = new Reference('http_client');
                if (null !== $value['timeout']) {
                    $httpClient = (new Definition(HttpClientInterface::class, [['timeout' => $value['timeout']]]))
                        ->setFactory([$httpClient, 'withOptions']);
                }

                $definition->setArgument('$httpClient', $httpClient);
            }

            // Prefixed so a device name like "cache" or "http_client" can't replace a core service
            $deviceDefinitions[$device] = $builder->setDefinition("storage.device.$device", $definition);
        }

        // Register Storage
        $storageService = $builder->setDefinition(Storage::class, new Definition(Storage::class, [
            '$default' => $config['default'],
            '$devices' => $deviceDefinitions,
        ]));

        if ('test' === $container->env()) {
            $storageService->setPublic(true);
        }
    }
}
