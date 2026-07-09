<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\Cloudflare;
use PHPUnit\Framework\TestCase;

/**
 * SigV4 query presigning is a local computation, so the URL shape can be asserted
 * offline with dummy credentials — no bucket and no network required.
 */
class PresignedPutTest extends TestCase
{
    private const ENDPOINT = 'https://example.r2.cloudflarestorage.com';
    private const DOMAIN = 'https://cdn.example.com';

    private function driver(): Cloudflare
    {
        return new Cloudflare(
            'test-access-key',
            'test-secret-key',
            'unit-test',
            '/',
            self::ENDPOINT,
            'auto',
            self::DOMAIN,
            'unit-test-private',
        );
    }

    public function testPresignedPutUrlTargetsTheBucketEndpoint(): void
    {
        $url = $this->driver()->getPresignedPutUrl('2026/07/recording.webm');
        $parts = parse_url($url);

        $this->assertSame('example.r2.cloudflarestorage.com', $parts['host']);
        $this->assertSame('/unit-test/2026/07/recording.webm', $parts['path']);

        parse_str($parts['query'], $query);
        $this->assertSame('AWS4-HMAC-SHA256', $query['X-Amz-Algorithm']);
        $this->assertArrayHasKey('X-Amz-Signature', $query);
        $this->assertArrayHasKey('X-Amz-Credential', $query);
        $this->assertArrayHasKey('X-Amz-Date', $query);
    }

    /**
     * A CDN domain fronts reads only. Uploads must always hit the S3 endpoint, so the PUT
     * presign never inherits the domain rewrite that Cloudflare::getPresignedUrl applies.
     */
    public function testPresignedPutUrlIgnoresTheCdnDomain(): void
    {
        $driver = $this->driver();

        $this->assertStringStartsWith(self::DOMAIN.'/', $driver->getPresignedUrl('2026/07/recording.webm'));
        $this->assertStringStartsWith(self::ENDPOINT.'/', $driver->getPresignedPutUrl('2026/07/recording.webm'));
    }

    public function testPrivateViewPresignsAgainstThePrivateBucket(): void
    {
        $url = $this->driver()->private()->getPresignedPutUrl('2026/07/recording.webm');

        $this->assertSame('/unit-test-private/2026/07/recording.webm', parse_url($url, PHP_URL_PATH));
    }

    public function testPresignedPutUrlHonoursTheExpiry(): void
    {
        $url = $this->driver()->getPresignedPutUrl('2026/07/recording.webm', new \DateTimeImmutable('+15 minutes'));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertGreaterThan(880, (int) $query['X-Amz-Expires']);
        $this->assertLessThanOrEqual(900, (int) $query['X-Amz-Expires']);
    }
}
