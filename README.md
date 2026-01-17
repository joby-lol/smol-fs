# smolFS

A security-focused filesystem abstraction library with strict path validation and bounded directory access.

## Installation
```bash
composer require joby-lol/smol-fs
```

## About

smolFS provides a simple, secure filesystem abstraction that prevents directory traversal attacks and enforces security boundaries. All file operations are confined within a defined root directory.

- **Security boundary enforcement**: All paths validated at entry points to prevent traversal attacks
- **Path normalization**: Handles relative paths, absolute paths, `..`, `.`, and mixed slashes safely
- **File locking**: Automatic file locking with exponential backoff retry for concurrent access
- **Fluent API**: Clean, chainable methods for file and directory operations
- **Relative path support**: Easy conversion between absolute and relative paths
- **Glob support**: Pattern matching for file and directory listing with brace expansion

## Basic Usage
```php
use Joby\Smol\Filesystem\Filesystem;

// Create a filesystem rooted at a specific directory
$fs = new Filesystem('/var/www/uploads');

// Work with files safely using relative paths
// Absolute paths can also be used, and will be validated
// Paths cannot escape the root of the Filesystem object
$file = $fs->file('user-data/document.txt', create: true);
$file->write('Hello, world!');

// Read it back
$content = $file->read();
```

## Creating a Filesystem

The `Filesystem` object represents a bounded directory tree. All operations are restricted to this root and its subdirectories.

```php
use Joby\Smol\Filesystem\Filesystem;

// Create filesystem at project root
$fs = new Filesystem('/path/to/project');

// Root must exist - throws FilesystemException if not
try {
    $fs = new Filesystem('/nonexistent/path');
} catch (FilesystemException $e) {
    // Handle error
}
```

## Working with Files

### Getting File Objects

Files can be accessed by relative or absolute paths. If `create: true`, returns a File object even if it doesn't exist yet so that you can create it at will.

```php
// Get existing file (returns null if not found)
$file = $fs->file('data/config.json');

// Get file for creation
$file = $fs->file('data/new-file.txt', create: true);

// File from subdirectory
$subdir = $fs->directory('uploads');
$file = $subdir->file('image.jpg');
```

### Reading Files

```php
$file = $fs->file('data.txt');

// Read entire file
$content = $file->read(); // returns string or false if not exists

// Check if file exists
if ($file->exists()) {
    $content = $file->read();
}

// File metadata
$size = $file->size();           // int|false
$modified = $file->modified();   // DateTime|null
$filename = $file->filename();   // 'data.txt'
$extension = $file->extension(); // 'txt'
```

### Writing Files

All write operations automatically create parent directories and use file locking.

```php
$file = $fs->file('output/result.txt', create: true);

// Write (replaces content)
$file->write('New content');

// Append to end
$file->append(' more text');

// Append a line (adds newline before if needed)
$file->appendLine('Log entry at ' . date('Y-m-d H:i:s'));

// Method chaining
$file->write('Initial content')
     ->append(' and more')
     ->appendLine('Final line');
```

### Deleting Files

```php
$file = $fs->file('temp/cache.txt');

// Delete if exists (safe - no error if missing)
$file->delete();
```

### File Paths

```php
$file = $fs->file('documents/report.pdf');

// Full system path
echo $file->path;
// /var/www/uploads/documents/report.pdf

// Path relative to filesystem root
echo $file->relativePath();
// documents/report.pdf

// Root directory
echo $file->root;
// /var/www/uploads/
```

## Working with Directories

### Getting Directory Objects

```php
// Get existing directory (returns null if not found)
$dir = $fs->directory('uploads');

// Get directory for creation
$dir = $fs->directory('new-folder', create: true);

// Nested directories
$subdir = $dir->directory('2024/january');
```

### Creating Directories

```php
$dir = $fs->directory('data/cache', create: true);

// Create on disk (with parents as needed)
$dir->create();

// Chaining
$fs->directory('reports/2024', create: true)
   ->create();
```

### Listing Files and Directories

```php
$dir = $fs->directory('uploads');

// Get all files
$files = $dir->files();

// Get all subdirectories  
$subdirs = $dir->directories();

// Files with glob pattern
$images = $dir->files('*.{jpg,png,gif}');
$logs = $dir->files('*.log');

// Directories with glob pattern
$yearDirs = $dir->directories('20*');

// Files with filter function
$largeFiles = $dir->files(null, fn($f) => $f->size() > 1000000);

// Combine glob and filter
$recentImages = $dir->files(
    '*.jpg',
    fn($f) => $f->modified() > new DateTime('-1 week')
);
```

### Directory Information

```php
$dir = $fs->directory('uploads');

// Check existence
$exists = $dir->exists();

// Directory metadata
$modified = $dir->modified();  // DateTime|null
$name = $dir->basename();      // 'uploads'

// Paths
echo $dir->path;              // /var/www/uploads/uploads
echo $dir->relativePath();    // uploads
```

### Deleting Directories

```php
$dir = $fs->directory('temp');

// Delete empty directory
$dir->delete();

// Delete with all contents (recursive)
$dir->delete(recursive: true);
```

## Listing Root Contents

The `Filesystem` object itself can list files and directories in its root:

```php
$fs = new Filesystem('/var/www/uploads');

// All files in root
$files = $fs->files();

// All directories in root
$dirs = $fs->directories();

// With patterns
$configFiles = $fs->files('*.{json,yml}');
$yearDirs = $fs->directories('20*');

// With filters
$recentFiles = $fs->files(null, fn($f) => $f->modified() > new DateTime('-1 day'));
```

## Copy and Move Operations

```php
$fs = new Filesystem('/var/www/data');

// Copy file
$fs->copy('source.txt', 'backup/source.txt', allow_overwrite: false);

// Copy with overwrite
$fs->copy('data.json', 'archive/data.json', allow_overwrite: true);

// Move file
$fs->move('uploads/temp.jpg', 'images/photo.jpg', allow_overwrite: false);

// Works with File objects too
$source = $fs->file('document.pdf');
$fs->copy($source, 'archive/document.pdf', allow_overwrite: false);
```

## Crossing the Security Boundary

While `copy()` and `move()` work within the filesystem's root, you can also move files in and out of the bounded directory. The caveat is that to avoid accidentally breaking isolation, the operations must explicitly use methdods that anchor one end of the operation in the Filesystem's root.

### Copying and Moving Files Out

Export files from the secured filesystem to anywhere else:
```php
$fs = new Filesystem('/var/www/uploads');

// Copy file out to arbitrary location
$fs->copyOut('user/avatar.jpg', '/tmp/backup.jpg', allow_overwrite: false);

// Move file out (removes from filesystem)
$fs->moveOut('temp/export.csv', '/var/exports/data.csv', allow_overwrite: true);

// Source path still validated against root
try {
    $fs->copyOut('../../etc/passwd', '/tmp/bad.txt', false);
} catch (FilesystemSecurityException $e) {
    // Prevented - source must be within filesystem root
}
```

### Copying and Moving Files In

Import files from arbitrary locations into the secured filesystem:
```php
$fs = new Filesystem('/var/www/uploads');

// Copy file in from anywhere
$fs->copyIn('/tmp/import.csv', 'data/imported.csv', allow_overwrite: false);

// Move file in (removes from source location)
$fs->moveIn('/tmp/upload.jpg', 'images/photo.jpg', allow_overwrite: true);

// Destination path still validated against root
try {
    $fs->copyIn('/tmp/file.txt', '../../escape.txt', false);
} catch (FilesystemSecurityException $e) {
    // Prevented - destination must be within filesystem root
}
```

### Handling Uploaded Files

For PHP uploaded files, use `moveIn()` with `allow_uploaded_files: true`:
```php
// In a file upload handler
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
    $tmpPath = $_FILES['upload']['tmp_name'];
    $filename = basename($_FILES['upload']['name']);
    
    $uploads = new Filesystem('/var/www/uploads');
    
    try {
        // Must explicitly allow uploaded files for security
        $uploads->moveIn(
            $tmpPath, 
            "user-{$userId}/{$filename}", 
            allow_overwrite: false,
            allow_uploaded_files: true
        );
        echo "File uploaded successfully!";
    } catch (FilesystemException $e) {
        echo "Upload failed: " . $e->getMessage();
    }
}
```

The `allow_uploaded_files` parameter is required as an explicit safeguard - it prevents accidentally moving uploaded files without proper validation. This forces you to consciously handle uploaded files differently from regular filesystem operations.

**Note**: `copyIn()` will throw an exception if you try to copy an uploaded file - you must use `moveIn()` instead, as uploaded files should always be moved, not copied.

## Nested Filesystems

Create a new `Filesystem` rooted at a subdirectory:

```php
$fs = new Filesystem('/var/www/data');

// Create filesystem rooted at subdirectory
// Creates the directory if it doesn't exist
$uploads = $fs->filesystem('uploads');

// Now all operations are relative to /var/www/data/uploads
$file = $uploads->file('image.jpg');
echo $file->path;
// /var/www/data/uploads/image.jpg

// Can nest further
$userUploads = $uploads->filesystem('user-123');
```

## Path Security and Validation

smolFS prevents directory traversal attacks by comprehensively normalizing and validating all paths. Both windows and unix filesystems are fully supported.

### Security Features

```php
$fs = new Filesystem('/var/www/uploads');

// These all throw FilesystemSecurityException:
try {
    $fs->file('../../../etc/passwd'); // Traversal above root
    $fs->file('/etc/passwd');         // Absolute path outside root  
    $fs->directory('data/../../etc'); // Relative traversal escaping root
    $fs->file("file\x00name.txt");    // Null byte injection
} catch (FilesystemSecurityException $e) {
    // Attack detected
}

// These are safe and work correctly:
$fs->file('data/../config.json');     // Normalizes to config.json
$fs->file('./data/file.txt');         // Normalizes to data/file.txt
$fs->file('data//double-slash.txt');  // Normalizes to data/double-slash.txt
```

### Path Normalization

All paths are normalized before use:
- Forward and backslashes converted to forward slashes
- Multiple consecutive slashes collapsed to single slash
- `.` and `..` resolved safely
- Absolute paths made relative to root
- Control characters rejected

```php
// All of these resolve to the same file:
$fs->file('data/file.txt');
$fs->file('./data/file.txt');
$fs->file('data//file.txt');
$fs->file('data/subfolder/../file.txt');
```

## File Locking

All read and write operations automatically acquire appropriate locks with retry logic. Failed lock acquisition throws a `FilesystemException`.

Locks use exponential backoff: 10ms, 20ms, 40ms, 80ms between attempts.

## Usage Patterns

### User File Storage

```php
// Create isolated storage per user
$storage = new Filesystem('/var/www/storage');
$userFs = $storage->filesystem("user-{$userId}");

// Enforces that user operations can only access their own files
$avatar = $userFs->file('avatar.jpg', create: true);
$avatar->write($uploadedImageData);

// Even if something provides malicious path, it's contained
$userFile = $userFs->file($_POST['filename']); // Safe from traversal attacks
```

### Configuration Management

```php
$config = new Filesystem('/etc/myapp');

// Read config
$settings = json_decode($config->file('settings.json')->read(), true);

// Update config
$config->file('cache.json', create: true)
       ->write(json_encode($cacheData, JSON_PRETTY_PRINT));
```

### Log File Rotation

```php
$logs = new Filesystem('/var/log/myapp');

// Append to daily log
$logFile = $logs->file(date('Y-m-d') . '.log', create: true);
$logFile->appendLine('[' . date('H:i:s') . '] ' . $message);

// Clean up old logs
$oldLogs = $logs->files('*.log', function($file) {
    return $file->modified() < new DateTime('-30 days');
});

foreach ($oldLogs as $log) {
    $log->delete();
}
```

### Safe Archive Extraction

```php
// Extract archive contents to bounded directory
$extractDir = new Filesystem('/tmp/extract-' . uniqid());
$extractDir->directory('', create: true)->create();

// Extract zip contents
$zip = new ZipArchive();
$zip->open($archivePath);

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    
    try {
        // Security: smolFS prevents escaping extract directory
        $file = $extractDir->file($filename, create: true);
        $file->write($zip->getFromIndex($i));
    } catch (FilesystemSecurityException $e) {
        // Malicious path in archive detected
        logSecurityEvent("Traversal attempt in archive: $filename");
    }
}
```

## PHP version

Fully cross-platform tested on PHP 8.3+, static analysis for PHP 8.1+.

## License

MIT License - See [LICENSE](LICENSE) file for details.