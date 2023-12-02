<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\Local;
use Cesurapp\StorageBundle\Storage\Storage;

class StorageServiceTest extends S3Base
{
    protected function init(): void
    {
        $this->client = self::getContainer()->get(Storage::class);
    }

    public function testStorageKey(): void
    {
        $this->assertInstanceOf(Local::class, $this->client->getClient());
        $this->assertInstanceOf(Local::class, $this->client->device('main'));
        $this->assertSame('main', $this->client->getStorageKey());
    }
}
