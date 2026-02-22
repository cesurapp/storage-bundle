<?php

namespace Cesurapp\StorageBundle\Driver;

use Cesurapp\StorageBundle\Client\DriverInterface;
use Cesurapp\StorageBundle\Client\SimpleS3Client;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class Local implements DriverInterface
{
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '\\/');
    }

    protected function getRoot(): string
    {
        return rtrim($this->root, '\\/');
    }

    protected function getPath(string $filename): string
    {
        return $this->getRoot().DIRECTORY_SEPARATOR.ltrim($filename, '\\/');
    }

    public function getClient(): SimpleS3Client|self
    {
        return $this;
    }

    /**
     * Upload.
     */
    public function upload(string $sourcePath, string $storagePath, array $metadata = []): bool
    {
        if (!file_exists($sourcePath)) {
            throw new FileNotFoundException(path: $sourcePath);
        }

        $storageDir = dirname($this->getPath($storagePath));
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $storageDir));
        }

        if (is_uploaded_file($sourcePath)) {
            if (!move_uploaded_file($sourcePath, $this->getPath($storagePath))) {
                throw new \RuntimeException('Can\'t upload file '.$sourcePath);
            }
        } elseif (!copy($sourcePath, $this->getPath($storagePath))) {
            throw new \RuntimeException('Can\'t upload file '.$sourcePath);
        }

        return true;
    }

    /**
     * Write file by given path.
     */
    public function write(string $content, string $storagePath, string $contentType = 'text/plain', array $metadata = []): bool
    {
        $path = $this->getPath($storagePath);

        if (!\file_exists(\dirname($path)) && !@\mkdir(\dirname($path), 0755, true)) {
            throw new \RuntimeException('Can\'t create directory '.\dirname($path));
        }

        return (bool) \file_put_contents($path, $content);
    }

    /**
     * Check if file exists.
     */
    public function exists(string $storagePath): bool
    {
        return file_exists($this->getPath($storagePath));
    }

    /**
     * Read file by given path.
     */
    public function download(string $storagePath): string
    {
        $path = $this->getPath($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('File Not Found');
        }

        return file_get_contents($path);
    }

    /**
     * Read file by given path.
     *
     * @return resource
     */
    public function downloadResource(string $storagePath)
    {
        $path = $this->getPath($storagePath);

        if (!file_exists($path)) {
            throw new \RuntimeException('File Not Found');
        }

        return \fopen($path, 'rb');
    }

    /**
     * Empty.
     */
    public function downloadChunk(string $storagePath): iterable
    {
        return [];
    }

    /**
     * URL.
     */
    public function getUrl(string $storagePath): string
    {
        return $this->getPath($storagePath);
    }

    /**
     * Signed URL.
     */
    public function getPresignedUrl(string $storagePath, ?\DateTimeImmutable $expires = null): string
    {
        $timestamp = $expires?->getTimestamp() ?? (time() + 3600);

        return $this->getPath($storagePath).'?'.http_build_query([
            't' => $timestamp,
            's' => $this->generateSignature($this->getPath($storagePath), $timestamp, $_ENV['APP_SECRET'] ?? 'default_secret'),
        ]);
    }

    public function validateSignedUrl(string $url, string $storagePath): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return false;
        }

        parse_str($query, $params);

        $expires = (int) ($params['t'] ?? 0);
        $signature = $params['s'] ?? '';

        if (!$expires || !$signature) {
            return false;
        }

        if (time() >= $expires) {
            return false;
        }

        return hash_equals(
            $this->generateSignature($this->getPath($storagePath), $expires, $_ENV['APP_SECRET'] ?? 'default_secret'),
            $signature
        );
    }

    private function generateSignature(string $path, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $path.':'.$timestamp, $secret);
    }

    /**
     * Delete file in given path, Return true on success and false on failure.
     *
     * @see http://php.net/manual/en/function.filesize.php
     */
    public function delete(string $storagePath): bool
    {
        return $this->deleteRecursive($this->getPath($storagePath), true);
    }

    private function deleteRecursive(string $path, bool $recursive = false): bool
    {
        if ($recursive && is_dir($path)) {
            $files = glob($path.'*', GLOB_MARK);

            foreach ($files as $file) {
                $this->deleteRecursive($file, true);
            }

            rmdir($path);
        } elseif (is_file($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Returns given file path its size.
     *
     * @see http://php.net/manual/en/function.filesize.php
     */
    public function getSize(string $storagePath): int
    {
        return filesize($this->getPath($storagePath));
    }

    /**
     * Returns given file path its mime type.
     *
     * @see http://php.net/manual/en/function.mime-content-type.php
     */
    public function getMimeType(string $storagePath): string
    {
        return mime_content_type($this->getPath($storagePath));
    }

    /**
     * Returns given file path its MD5 hash value.
     *
     * @see http://php.net/manual/en/function.md5-file.php
     */
    public function getFileHash(string $path): string
    {
        return md5_file($this->getPath($path));
    }

    /**
     * Get directory size in bytes.
     *
     * Return -1 on error
     *
     * Based on http://www.jonasjohn.de/snippets/php/dir-size.htm
     */
    public function getDirectorySize(string $path): int
    {
        $size = 0;

        $directory = opendir($path);

        if (!$directory) {
            return -1;
        }

        while (($file = \readdir($directory)) !== false) {
            // Skip file pointers
            if ('.' === $file[0]) {
                continue;
            }

            // Go recursive down, or add the file size
            if (is_dir($path.$file)) {
                $size += $this->getDirectorySize($path.$file.DIRECTORY_SEPARATOR);
            } else {
                $size += filesize($path.$file);
            }
        }

        closedir($directory);

        return $size;
    }

    /**
     * Get Partition Free Space.
     *
     * disk_free_space — Returns available space on filesystem or disk partition
     */
    public function getPartitionFreeSpace(): float
    {
        return disk_free_space($this->getRoot());
    }

    /**
     * Get Partition Total Space.
     *
     * disk_total_space — Returns the total size of a filesystem or disk partition
     */
    public function getPartitionTotalSpace(): float
    {
        return disk_total_space($this->getRoot());
    }

    /**
     * Move file from given source to given path, Return true on success and false on failure.
     *
     * @see http://php.net/manual/en/function.filesize.php
     */
    public function move(string $source, string $target): bool
    {
        $target = $this->getRoot().DIRECTORY_SEPARATOR.ltrim($target, '\\/');

        if (!file_exists(\dirname($target)) && !@\mkdir(\dirname($target), 0755, true)) {
            throw new \RuntimeException('Can\'t create directory '.\dirname($target));
        }

        if (\rename($source, $target)) {
            return true;
        }

        return false;
    }

    /**
     * Delete files in given path, path must be a directory. Return true on success and false on failure.
     */
    public function deletePath(string $path): bool
    {
        $path = $this->getRoot().DIRECTORY_SEPARATOR.$path;

        if (is_dir($path)) {
            $files = \glob($path.'*', GLOB_MARK);

            foreach ($files as $file) {
                $this->delete($file);
            }

            rmdir($path);

            return true;
        }

        return false;
    }
}
