<?php

namespace Cesurapp\StorageBundle\Driver;

use Cesurapp\StorageBundle\Client\AbstractDriver;
use Cesurapp\StorageBundle\Client\SimpleS3Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BackBlaze extends AbstractDriver
{
    /**
     * Regions.
     */
    public const US_WEST_001 = 'us-west-001';
    public const US_WEST_002 = 'us-west-002';
    public const US_WEST_003 = 'us-west-003';
    public const US_WEST_004 = 'us-west-004';
    public const EU_CENTRAL_001 = 'eu-central-001';
    public const EU_CENTRAL_002 = 'eu-central-002';
    public const EU_CENTRAL_003 = 'eu-central-003';
    public const EU_CENTRAL_004 = 'eu-central-004';

    public function __construct(
        protected string $accessKey,
        protected string $secretKey,
        protected string $bucket,
        protected string $root,
        protected string $endPoint = '',
        protected string $region = 'auto',
        protected string $domain = '',
        ?HttpClientInterface $httpClient = null,
    ) {
        // @phpstan-ignore-next-line
        $this->client = new SimpleS3Client([
            'accessKeyId' => $this->accessKey,
            'accessKeySecret' => $this->secretKey,
            'region' => $this->region,
            'endpoint' => "https://s3.$this->region.backblazeb2.com",
            'pathStyleEndpoint' => true,
            'httpClient' => $httpClient,
        ]);

        parent::__construct($this->accessKey, $this->secretKey, $this->bucket, $this->root, $this->endPoint, $this->region, $this->domain);
    }
}
