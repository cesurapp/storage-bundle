<?php

namespace Cesurapp\StorageBundle\Tests;

use Cesurapp\StorageBundle\Driver\Local;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LocalTest extends WebTestCase
{
    protected ?Local $object;

    protected string $rootDir;

    public function setUp(): void
    {
        $this->rootDir = self::createKernel()->getCacheDir();
        $this->object = new Local($this->rootDir);
    }

    public function testWrite(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text.txt'));
        $this->assertTrue($this->object->exists('text.txt'));
        $this->assertTrue(is_readable($this->object->getUrl('text.txt')));

        $this->object->delete('text.txt');
    }

    public function testDownload(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text-for-read.txt'));
        $this->assertEquals('Hello World', $this->object->download('text-for-read.txt'));

        $this->object->delete('text-for-read.txt');
    }

    public function testDownloadResource(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text-for-read.txt'));
        $this->assertIsResource($this->object->downloadResource('text-for-read.txt'));
    }

    public function testFileExists(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text-for-test-exists.txt'));
        $this->assertTrue($this->object->exists('text-for-test-exists.txt'));
        $this->assertFalse($this->object->exists('text-for-test-doesnt-exist.txt'));

        $this->object->delete('text-for-test-exists.txt');
    }

    public function testMove(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text-for-move.txt'));
        $this->assertEquals('Hello World', $this->object->download('text-for-move.txt'));
        $this->assertTrue($this->object->move($this->object->getUrl('text-for-move.txt'), 'text-for-move-new.txt'));
        $this->assertEquals('Hello World', $this->object->download('text-for-move-new.txt'));
        $this->assertFileDoesNotExist($this->object->getUrl('text-for-move.txt'));
        $this->assertFalse(is_readable($this->object->getUrl('text-for-move.txt')));
        $this->assertFileExists($this->object->getUrl('text-for-move-new.txt'));
        $this->assertTrue(is_readable($this->object->getUrl('text-for-move-new.txt')));

        $this->object->delete('text-for-move-new.txt');
    }

    public function testDelete(): void
    {
        $this->assertTrue($this->object->write('Hello World', 'text-for-delete.txt'));
        $this->assertEquals('Hello World', $this->object->download('text-for-delete.txt'));
        $this->assertTrue($this->object->delete('text-for-delete.txt'));
        $this->assertFileDoesNotExist($this->object->getUrl('text-for-delete.txt'));
        $this->assertFalse(is_readable($this->object->getUrl('text-for-delete.txt')));
    }
}
