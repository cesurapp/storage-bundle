<?php

namespace Cesurapp\StorageBundle\Client;

interface DriverInterface
{
    public function getClient(): SimpleS3Client|self;

    public function upload(string $sourcePath, string $storagePath, array $metadata = []): bool;

    public function write(string $content, string $storagePath, string $contentType = 'text/plain', array $metadata = []): bool;

    public function exists(string $storagePath): bool;

    public function download(string $storagePath): string;

    /**
     * @return resource
     */
    public function downloadResource(string $storagePath);

    public function downloadChunk(string $storagePath): iterable;

    public function getUrl(string $storagePath): string;

    public function getPresignedUrl(string $storagePath, ?\DateTimeImmutable $expires = null): string;

    public function getPresignedPutUrl(string $storagePath, ?\DateTimeImmutable $expires = null): string;

    public function delete(string $storagePath): bool;

    /**
     * Deletes the given files, never a directory or prefix. A file that doesn't exist counts as deleted,
     * so calling again with the same paths is safe.
     *
     * @param string[] $storagePaths
     *
     * @return string[] the paths that could not be deleted, in the order given
     *
     * @throws \RuntimeException when the storage can't be reached; paths sent before it may already be deleted
     */
    public function deleteMany(array $storagePaths): array;

    public function getSize(string $storagePath): int;

    public function getMimeType(string $storagePath): string;

    public function getDomain(): ?string;

    public function private(): self;

    /**
     * A copy of the device whose HTTP requests take this timeout, the HTTP client's "timeout" option:
     * for a large upload, say. Under swoole-bundle an upload must be sent within it.
     */
    public function withTimeout(float $seconds): self;
}
