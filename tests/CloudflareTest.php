<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\Cloudflare;
use Symfony\Component\HttpClient\HttpClient;

class CloudflareTest extends S3Base
{
    protected function init(): void
    {
        $accessKey = $_SERVER['CLOUDFLARE_R2_ACCESS_KEY'] ?? '';
        $secretKey = $_SERVER['CLOUDFLARE_R2_SECRET'] ?? '';
        $endPoint = $_SERVER['CLOUDFLARE_R2_ENDPOINT'] ?? '';
        $bucket = $_SERVER['CLOUDFLARE_R2_BUCKET'] ?? 'unit-test';

        if (!$accessKey) {
            $this->markTestSkipped();
        }

        $this->client = new Cloudflare($accessKey, $secretKey, $bucket, '/', $endPoint);
    }

    /**
     * Round-trip: presign a PUT, upload straight to it with a plain HTTP client carrying no
     * credentials, and confirm the object landed with the Content-Type the uploader chose.
     */
    public function testPresignedPutUrlUpload(): void
    {
        $response = HttpClient::create()->request('PUT', $this->client->getPresignedPutUrl('testing/presigned.txt'), [
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'Hello World',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('Hello World', $this->client->download('testing/presigned.txt'));
        $this->assertEquals('text/plain', $this->client->getMimeType('testing/presigned.txt'));
        $this->assertEquals(204, $this->client->delete('testing/presigned.txt'));
    }
}
