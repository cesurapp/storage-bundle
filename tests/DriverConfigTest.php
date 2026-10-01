<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\BackBlaze;
use Cesurapp\StorageBundle\Driver\Cloudflare;
use Cesurapp\StorageBundle\StorageBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Driver construction and bundle wiring, checked offline with dummy credentials.
 */
class DriverConfigTest extends TestCase
{
    public function testBackBlazeTargetsTheRegionEndpoint(): void
    {
        $driver = new BackBlaze('key', 'secret', 'unit-test', '/', '', BackBlaze::US_WEST_004);

        $this->assertSame('https://s3.us-west-004.backblazeb2.com/unit-test/file.txt', $driver->getUrl('file.txt'));
    }

    public function testBackBlazeHonoursACustomEndpoint(): void
    {
        $driver = new BackBlaze('key', 'secret', 'unit-test', '/', 'https://b2.example.com', BackBlaze::US_WEST_004);

        $this->assertSame('https://b2.example.com/unit-test/file.txt', $driver->getUrl('file.txt'));
    }

    public function testBackBlazeRequiresARegion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BackBlaze('key', 'secret', 'unit-test', '/');
    }

    public function testCdnPresignedUrlHonoursTheExpiry(): void
    {
        $driver = new Cloudflare('key', 'secret', 'unit-test', '/', 'https://example.r2.cloudflarestorage.com', 'auto', 'https://cdn.example.com');
        $url = $driver->getPresignedUrl('file.txt', new \DateTimeImmutable('+7 days'));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://cdn.example.com/file.txt?', $url);
        $this->assertGreaterThan(604000, (int) $query['X-Amz-Expires']);
    }

    public function testBundleRegistersEveryDriverUnderAPrefixedId(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'prod');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());

        (new StorageBundle())->getContainerExtension()->load([[
            'default' => 'r2',
            'devices' => [
                'r2' => ['driver' => 'cloudflare', 'root' => '/'],
                'b2' => ['driver' => 'backblaze', 'root' => '/', 'region' => BackBlaze::US_WEST_004],
            ],
        ]], $builder);

        $this->assertSame(Cloudflare::class, $builder->getDefinition('storage.device.r2')->getClass());
        $this->assertSame(BackBlaze::class, $builder->getDefinition('storage.device.b2')->getClass());
        $this->assertFalse($builder->hasDefinition('r2'));
    }

    public function testDeviceTimeoutReachesTheHttpClient(): void
    {
        $timeouts = [];
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'prod');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $builder->set('http_client', new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return new MockResponse();
        }));

        (new StorageBundle())->getContainerExtension()->load([[
            'default' => 'r2',
            'devices' => [
                'r2' => ['driver' => 'cloudflare', 'root' => '/', 'endPoint' => 'https://example.r2.cloudflarestorage.com', 'timeout' => 30],
                'b2' => ['driver' => 'backblaze', 'root' => '/', 'region' => BackBlaze::US_WEST_004],
            ],
        ]], $builder);
        $builder->getDefinition('storage.device.r2')->setPublic(true);
        $builder->getDefinition('storage.device.b2')->setPublic(true);
        $builder->compile();

        $builder->get('storage.device.r2')->write('Hello World', 'text.txt');
        $builder->get('storage.device.b2')->write('Hello World', 'text.txt');

        $this->assertSame([30.0, (float) ini_get('default_socket_timeout')], $timeouts);
    }

    public function testWithTimeoutLeavesTheDeviceAsItIs(): void
    {
        $timeouts = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$timeouts) {
            $timeouts[] = [$options['timeout'], parse_url($url, PHP_URL_PATH)];

            return new MockResponse();
        });
        $driver = new Cloudflare('key', 'secret', 'public', '/', 'https://example.r2.cloudflarestorage.com', 'auto', '', 'private', $httpClient->withOptions(['timeout' => 30]));

        $driver->withTimeout(300)->write('Hello World', 'text.txt');
        $driver->withTimeout(300)->private()->write('Hello World', 'text.txt');
        $driver->private()->write('Hello World', 'text.txt');
        $driver->write('Hello World', 'text.txt');

        $this->assertSame([
            [300.0, '/public/text.txt'],
            [300.0, '/private/text.txt'],
            [30.0, '/private/text.txt'],
            [30.0, '/public/text.txt'],
        ], $timeouts);
    }
}
