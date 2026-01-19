# Storage Bundle Usage Guidelines

## 1. Storage Providers

### What is a Provider?

A storage provider (driver) is an adapter that implements the `DriverInterface` to handle file operations on different storage backends.

### Available Providers

- **local**: Filesystem-based storage
- **cloudflare**: Cloudflare R2 (S3-compatible)
- **backblaze**: BackBlaze B2 (S3-compatible)

### Configuration

Providers are configured in `config/packages/storage.yaml`:

```yaml
storage:
  default: main  # Default device key
  devices:
    local:
      driver: local
      root: "%kernel.project_dir%/storage/files"

    main:
      driver: cloudflare
      root: /
      accessKey: "your_access_key"
      secretKey: "your_secret_key"
      bucket: "bucket_name"
      region: "auto"
      endPoint: "https://endpoint.r2.cloudflarestorage.com"
```

### Switching Providers

```php
use Cesurapp\StorageBundle\Storage\Storage;

// Use default provider
$storage->upload('source.jpg', 'destination.jpg');

// Use specific provider
$storage->device('local')->upload('source.jpg', 'destination.jpg');
```

### Naming Conventions

- Device keys: lowercase, alphanumeric, underscores allowed
- Driver values: `local`, `cloudflare`, `backblaze`

### Minimal Example

```php
public function upload(Storage $storage): void
{
    // Uses default device
    $storage->upload('/tmp/file.jpg', 'uploads/file.jpg');

    // Uses specific device
    $storage->device('local')->upload('/tmp/file.jpg', 'uploads/file.jpg');
}
```

---

## 2. File Upload

### Accepted Input Types

- **File path**: Absolute path to existing file on filesystem
- **Uploaded file**: PHP uploaded file (detected via `is_uploaded_file()`)

### Upload Entry Point

```php
$storage->upload(string $sourcePath, string $storagePath, array $metadata = []): bool
```

### Required Parameters

- `$sourcePath`: Absolute path to source file
- `$storagePath`: Relative destination path within storage root

### Optional Parameters

- `$metadata`: Array of S3 metadata (cloud providers only)
  - `ContentType`: MIME type (auto-detected if not provided)
  - `CacheControl`: Cache directives
  - Other S3-compatible metadata fields

### Minimal Example

```php
// Basic upload
$storage->upload('/tmp/photo.jpg', 'users/123/photo.jpg');

// Upload with metadata (cloud only)
$storage->upload('/tmp/photo.jpg', 'users/123/photo.jpg', [
    'ContentType' => 'image/jpeg',
    'CacheControl' => 'max-age=3600'
]);

// Upload PHP uploaded file
$storage->upload($_FILES['photo']['tmp_name'], 'users/123/photo.jpg');
```

---

## 3. File Retrieval

### How to Retrieve Files

```php
// Read as string
$content = $storage->download('path/to/file.jpg');

// Read as resource
$resource = $storage->downloadResource('path/to/file.jpg');

// Read as chunks (cloud only)
$chunks = $storage->downloadChunk('path/to/file.jpg');
```

### URL vs Stream Usage

**URL (getUrl)**:
- Returns path/URL for direct access
- Local: filesystem path
- Cloud: public URL

**Stream (download methods)**:
- Downloads file content
- Use for file processing, serving downloads, or copying

### Access Constraints

- Files must exist (use `exists()` to check)
- Local: filesystem read permissions required
- Cloud: bucket read permissions required

### Minimal Example

```php
// Check existence
if (!$storage->exists('uploads/file.pdf')) {
    throw new \RuntimeException('File not found');
}

// Download as string
$content = $storage->download('uploads/file.pdf');

// Stream to browser
$resource = $storage->downloadResource('uploads/file.pdf');
header('Content-Type: application/pdf');
fpassthru($resource);
fclose($resource);

// Get URL for embed
$url = $storage->getUrl('uploads/image.jpg');
echo "<img src='$url'>";
```

---

## 4. File Deletion

### How Files Are Deleted

```php
$success = $storage->delete('path/to/file.jpg');
```

Returns:
- **Local**: `true` on success, `false` on failure
- **Cloud**: `true` if HTTP 204 response

### Soft vs Hard Delete

STORAGE-BUNDLE performs **hard deletes** only. No soft delete functionality is included.

### Cleanup Expectations

- **Local**: File is removed from filesystem
- **Cloud**: Object is permanently deleted from bucket
- **Directories**: Local driver removes empty directories after file deletion

### Minimal Example

```php
// Delete single file
$storage->delete('uploads/old-file.jpg');

// Delete with verification
if ($storage->exists('uploads/temp.jpg')) {
    $storage->delete('uploads/temp.jpg');
}

// Batch delete
$files = ['file1.jpg', 'file2.jpg', 'file3.jpg'];
foreach ($files as $file) {
    $storage->delete("uploads/$file");
}
```

---

## 5. Metadata Handling

### Available Metadata Fields

```php
// File size (bytes)
$size = $storage->getSize('path/to/file.jpg');

// MIME type
$mime = $storage->getMimeType('path/to/file.jpg');

// Check existence
$exists = $storage->exists('path/to/file.jpg');
```

### How Metadata is Stored and Accessed

- **Local**: Retrieved via PHP filesystem functions
- **Cloud**: Retrieved via S3 HEAD request

No metadata is persisted in a database by STORAGE-BUNDLE.

### Integrity Checks

**Local Driver Only**: MD5 hash for file integrity

```php
$driver = $storage->device('local');
$hash = $driver->getFileHash('path/to/file.jpg');
```

**Cloud drivers do not support hash retrieval** through STORAGE-BUNDLE API.

### Minimal Example

```php
// Get file metadata
$file = 'uploads/document.pdf';

if ($storage->exists($file)) {
    $size = $storage->getSize($file);
    $mime = $storage->getMimeType($file);

    echo "Size: " . ($size / 1024) . " KB\n";
    echo "Type: $mime\n";
}

// Integrity check (local only)
$localDriver = $storage->device('local');
$hash = $localDriver->getFileHash('uploads/document.pdf');
echo "MD5: $hash\n";
```

---

## 6. Conventions & Rules

### Naming Conventions

**Device Keys**:
- Lowercase alphanumeric characters
- Use underscores for word separation
- Examples: `main`, `backup_storage`, `cdn_primary`

**Storage Paths**:
- Use forward slashes (`/`) as separators
- Relative to configured `root`
- Leading/trailing slashes are normalized automatically
- Examples: `users/123/photo.jpg`, `documents/2024/report.pdf`

**Driver Values**:
- Exactly: `local`, `cloudflare`, or `backblaze`

### Do / Don't Rules

**DO**:
- Validate files before upload at application level
- Check file existence with `exists()` before operations
- Use forward slashes in all paths
- Implement custom naming strategies at application level
- Handle exceptions from storage operations
- Close resources after using `downloadResource()`

**DON'T**:
- Don't rely on bundle for validation
- Don't assume automatic file naming/renaming
- Don't expect soft delete functionality
- Don't use backslashes in paths
- Don't assume cloud URLs are pre-signed by default
- Don't use chunk download with local driver (returns empty)

### Security Considerations

**File Upload**:
- Always validate MIME types before upload
- Limit file sizes at application level
- Sanitize user-provided filenames
- Use non-predictable filenames for sensitive files

**Access Control**:
- Configure cloud bucket ACLs separately
- Set appropriate filesystem permissions for local storage
- Use pre-signed URLs for temporary cloud access
- Never expose absolute local paths to users

**Path Traversal**:
- Never use user input directly in storage paths without sanitization
- Validate paths don't contain `../` sequences
- Use whitelisted characters for path components

### Common Mistakes to Avoid

**Mistake**: Expecting validation errors from upload methods
- **Correct**: Validate files before calling upload

**Mistake**: Using relative paths or `../` in storage paths
- **Correct**: Use forward slashes and relative paths from root

**Mistake**: Assuming `getUrl()` returns HTTP URLs for local storage
- **Correct**: Local driver returns filesystem paths

**Mistake**: Not checking return values
- **Correct**: Always check boolean returns and handle failures

**Mistake**: Forgetting to close resources
- **Correct**: Use `fclose()` after `downloadResource()`

**Mistake**: Using cloud-specific features with local driver
- **Correct**: Check driver type or handle gracefully

**Mistake**: Expecting automatic directory cleanup
- **Correct**: Implement manual cleanup for temporary files

### Minimal Security Example

```php
use Symfony\Component\String\Slugger\SluggerInterface;

public function secureUpload(UploadedFile $file, SluggerInterface $slugger, Storage $storage): string
{
    // 1. Validate
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file->getMimeType(), $allowedMimes)) {
        throw new \RuntimeException('Invalid file type');
    }

    // 2. Sanitize filename
    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeFilename = $slugger->slug($originalName);

    // 3. Generate non-predictable name
    $fileName = sprintf(
        '%s/%s-%s.%s',
        date('Y/m/d'),
        $safeFilename,
        uniqid(),
        $file->guessExtension()
    );

    // 4. Upload
    $storage->upload($file->getPathname(), $fileName);

    return $fileName;
}
```
