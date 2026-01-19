# Storage Bundle

[![App Tester](https://github.com/cesurapp/storage-bundle/actions/workflows/testing.yaml/badge.svg)](https://github.com/cesurapp/storage-bundle/actions/workflows/testing.yaml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?logo=Unlicense)](LICENSE.md)

Symfony file storage abstraction bundle with support for local and cloud storage providers.

- **No database dependency** - Pure file storage operations
- **S3-compatible** - Uses [async-aws/s3](https://github.com/async-aws/aws) for cloud storage
- **Multiple devices** - Configure and use multiple storage providers simultaneously
- **Fully tested** - Complete test coverage for all drivers

## Supported Drivers

- **Local** - Filesystem-based storage
- **Cloudflare R2** - S3-compatible object storage
- **BackBlaze B2** - S3-compatible object storage

## Installation

Requires **Symfony 8** and **PHP 8.4+**

```bash
composer require cesurapp/storage-bundle
```

## Configuration

Create `config/packages/storage.yaml`:

```yaml
storage:
  default: main  # Default storage device
  devices:
    # Local filesystem storage
    local:
      driver: local
      root: "%kernel.project_dir%/storage/files"

    # Cloudflare R2 storage
    main:
      driver: cloudflare
      root: /
      accessKey: "your_access_key"
      secretKey: "your_secret_key"
      bucket: "your_bucket_name"
      region: "auto"
      endPoint: "https://your-account-id.r2.cloudflarestorage.com"

    # BackBlaze B2 storage
    backblaze:
      driver: backblaze
      root: /
      accessKey: "your_key_id"
      secretKey: "your_application_key"
      bucket: "your_bucket_name"
      region: "us-west-001"  # See BackBlaze regions
```

### Available Regions (BackBlaze)

```php
use Cesurapp\StorageBundle\Driver\BackBlaze;

BackBlaze::US_WEST_001;      // us-west-001
BackBlaze::US_WEST_002;      // us-west-002
BackBlaze::US_WEST_003;      // us-west-003
BackBlaze::US_WEST_004;      // us-west-004
BackBlaze::EU_CENTRAL_001;   // eu-central-001
BackBlaze::EU_CENTRAL_002;   // eu-central-002
BackBlaze::EU_CENTRAL_003;   // eu-central-003
BackBlaze::EU_CENTRAL_004;   // eu-central-004
```

## Usage

```php
use Cesurapp\StorageBundle\Storage\Storage;

class FileController
{
    public function upload(Storage $storage): void
    {
        // Upload file (uses default device)
        $storage->upload('/tmp/photo.jpg', 'users/123/photo.jpg');

        // Write content directly
        $storage->write('Hello World', 'documents/hello.txt', 'text/plain');

        // Check if file exists
        if ($storage->exists('users/123/photo.jpg')) {
            // Get file URL
            $url = $storage->getUrl('users/123/photo.jpg');

            // Get file size
            $size = $storage->getSize('users/123/photo.jpg');

            // Get MIME type
            $mime = $storage->getMimeType('users/123/photo.jpg');
        }

        // Download file content
        $content = $storage->download('users/123/photo.jpg');

        // Download as resource
        $resource = $storage->downloadResource('users/123/photo.jpg');

        // Download as chunks (cloud only)
        foreach ($storage->downloadChunk('large-file.zip') as $chunk) {
            echo $chunk;
        }

        // Delete file
        $storage->delete('users/123/photo.jpg');
    }
}
```

### Using Specific Storage Device

```php
// Use a specific storage device instead of default
$storage->device('local')->upload('/tmp/file.pdf', 'documents/file.pdf');
$storage->device('backblaze')->upload('/tmp/backup.zip', 'backups/backup.zip');
```

### Upload with Metadata (Cloud Only)

```php
$storage->upload('/tmp/photo.jpg', 'users/123/photo.jpg', [
    'ContentType' => 'image/jpeg',
    'CacheControl' => 'max-age=3600',
    'Metadata' => [
        'user-id' => '123',
        'uploaded-by' => 'admin'
    ]
]);
```

### Pre-signed URLs (Cloud Only)

```php
// Generate temporary access URL (expires in 1 hour)
$client = $storage->device('main')->getClient();
$presignedUrl = $client->getPresignedUrl(
    'bucket-name',
    'path/to/file.jpg',
    new \DateTimeImmutable('+1 hour')
);
```

### Local Driver Specific Methods

```php
$localDriver = $storage->device('local');

// Get file hash (MD5)
$hash = $localDriver->getFileHash('documents/file.pdf');

// Get directory size
$size = $localDriver->getDirectorySize('users/123/');

// Get partition free space
$freeSpace = $localDriver->getPartitionFreeSpace();

// Get partition total space
$totalSpace = $localDriver->getPartitionTotalSpace();

// Move file
$localDriver->move('/tmp/file.pdf', 'documents/moved-file.pdf');

// Delete directory
$localDriver->deletePath('temp/uploads/');
```

## Testing

Tests for cloud providers require access credentials. Tests without credentials are automatically skipped.

### Configure Test Credentials

Edit `phpunit.xml.dist`:

```xml
<!-- BackBlaze Test Keys -->
<server name="BACKBLAZE_ACCESS_KEY" value="your_key_id"/>
<server name="BACKBLAZE_SECRET" value="your_application_key"/>
<server name="BACKBLAZE_BUCKET" value="your_test_bucket"/>

<!-- CloudFlare R2 Test Keys -->
<server name="CLOUDFLARE_R2_ACCESS_KEY" value="your_access_key"/>
<server name="CLOUDFLARE_R2_SECRET" value="your_secret_key"/>
<server name="CLOUDFLARE_R2_ENDPOINT" value="https://your-account-id.r2.cloudflarestorage.com"/>
<server name="CLOUDFLARE_R2_BUCKET" value="your_test_bucket"/>
```

### Run Tests

```bash
composer test
```

## API Reference

For detailed usage guidelines and best practices, see [GUIDELINES.md](GUIDELINES.md)

## License

MIT License - see [LICENSE](LICENSE) for details