<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\Cloudflare;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * DeleteObjects requests and responses, checked offline against a mocked HTTP client.
 */
class DeleteManyTest extends TestCase
{
    private function driver(MockHttpClient $httpClient): Cloudflare
    {
        return new Cloudflare('key', 'secret', 'unit-test', '/uploads', 'https://example.r2.cloudflarestorage.com', 'auto', '', '', $httpClient);
    }

    public function testSendsBatchesOf1000AndMapsErrorsBackToPaths(): void
    {
        $requests = [];
        $responses = [
            '<DeleteResult><Error><Key>uploads/f5.txt</Key><Code>AccessDenied</Code><Message>Access Denied</Message></Error></DeleteResult>',
            '<DeleteResult/>',
        ];
        $driver = $this->driver(new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses) {
            $requests[] = [$method, $url, $options['normalized_headers'], new \SimpleXMLElement($options['body'])];

            return new MockResponse(array_shift($responses));
        }));

        // 1001 files, the 6th one also spelled with a leading slash
        $paths = array_map(static fn (int $i) => "f$i.txt", range(0, 1000));
        $paths[] = '/f5.txt';

        $this->assertSame(['f5.txt', '/f5.txt'], $driver->deleteMany($paths));
        $this->assertCount(2, $requests);
        [$method, $url, $headers, $body] = $requests[0];
        $this->assertSame('POST', $method);
        $this->assertSame('https://example.r2.cloudflarestorage.com/unit-test?delete=', $url);
        $this->assertArrayHasKey('content-md5', $headers);
        $this->assertSame('true', (string) $body->Quiet);
        $this->assertCount(1000, $body->Object);
        $this->assertSame('uploads/f0.txt', (string) $body->Object[0]->Key);
        $this->assertCount(1, $requests[1][3]->Object);
        $this->assertSame('uploads/f1000.txt', (string) $requests[1][3]->Object[0]->Key);
    }

    public function testPathsNamingNoFileFailWithoutARequest(): void
    {
        $driver = $this->driver(new MockHttpClient(fn () => $this->fail('No request expected')));

        $this->assertSame(['', '/', '\\'], $driver->deleteMany(['', '/', '\\', '']));
        $this->assertSame([], $driver->deleteMany([]));
    }

    /**
     * swoole-bundle's bridge reports a timeout as status -2 with an empty body, which async-aws doesn't treat as an error.
     */
    public function testUnreachableStorageThrows(): void
    {
        $driver = $this->driver(new MockHttpClient(new MockResponse('', ['http_code' => -2])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs('Deleting objects failed with HTTP status -2.');
        $driver->deleteMany(['a.txt']);
    }

    public function testErrorResponseThrows(): void
    {
        $driver = $this->driver(new MockHttpClient(new MockResponse('<Error><Code>AccessDenied</Code></Error>', ['http_code' => 403])));

        $this->expectException(\RuntimeException::class);
        $driver->deleteMany(['a.txt']);
    }

    public function testErrorForAKeyNotRequestedThrows(): void
    {
        $driver = $this->driver(new MockHttpClient(new MockResponse('<DeleteResult><Error><Key>other.txt</Key><Code>AccessDenied</Code></Error></DeleteResult>')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs('Deleting objects failed for "other.txt", which was not requested.');
        $driver->deleteMany(['a.txt']);
    }
}
