<?php

namespace Cesurapp\StorageBundle\Driver;

use Cesurapp\StorageBundle\Client\AbstractDriver;
use Cesurapp\StorageBundle\Client\SimpleS3Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Cloudflare extends AbstractDriver
{
    public function __construct(
        protected string $accessKey,
        protected string $secretKey,
        protected string $bucket,
        protected string $root,
        protected string $endPoint = '',
        protected string $region = '',
        protected string $domain = '',
        protected string $bucketPrivate = '',
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient;
        $this->client = $this->createClient($httpClient);

        parent::__construct($this->accessKey, $this->secretKey, $this->bucket, $this->root, $this->endPoint, $this->region, $this->domain, $this->bucketPrivate);
    }

    protected function createClient(?HttpClientInterface $httpClient): SimpleS3Client
    {
        return new SimpleS3Client([
            'accessKeyId' => $this->accessKey,
            'accessKeySecret' => $this->secretKey,
            'region' => $this->region,
            'endpoint' => $this->endPoint,
            'pathStyleEndpoint' => 'true',
        ], null, $httpClient);
    }

    public function getUrl(string $storagePath): string
    {
        if (!$this->domain || $this->isPrivate) {
            return parent::getUrl($storagePath);
        }

        return sprintf('%s/%s', $this->domain, $this->getPath($storagePath));
    }

    public function getPresignedUrl(string $storagePath, ?\DateTimeImmutable $expires = null): string
    {
        if (!$this->domain || $this->isPrivate) {
            return parent::getPresignedUrl($storagePath, $expires);
        }

        $path = $this->getPath($storagePath);

        return sprintf('%s/%s?%s', $this->domain, $path, parse_url($this->getClient()->getPresignedUrl($this->bucket, $path, $expires), PHP_URL_QUERY));
    }
}
