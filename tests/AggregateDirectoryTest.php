<?php

namespace Joby\Smol\Filesystem;

use PHPUnit\Framework\TestCase;

class AggregateDirectoryTest extends TestCase
{

    private string $tempPath1;

    private string $tempPath2;

    private Filesystem $fs1;

    private Filesystem $fs2;

    private AggregateFilesystem $aggregateFs;

    protected function setUp(): void
    {
        // Create unique temporary directories for isolation
        $this->tempPath1 = sys_get_temp_dir() . '/smol_fs_test_1_' . uniqid();
        $this->tempPath2 = sys_get_temp_dir() . '/smol_fs_test_2_' . uniqid();

        mkdir($this->tempPath1, 0777, true);
        mkdir($this->tempPath2, 0777, true);

        $this->fs1 = new Filesystem($this->tempPath1);
        $this->fs2 = new Filesystem($this->tempPath2);

        $this->aggregateFs = new AggregateFilesystem($this->fs1, $this->fs2);
    }

    protected function tearDown(): void
    {
        // Clean up physical temporary directories after each test
        $this->removeDirectory($this->tempPath1);
        $this->removeDirectory($this->tempPath2);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test single-target operations like create(), exists(), relativePath(), and basename().
     */
    public function test_delegates_single_target_operations_to_first_directory(): void
    {
        // Create directory in fs1 root
        $dir1 = $this->fs1->directory('uploads', create: true)->create();
        $dir2 = $this->fs2->directory('uploads', create: true); // Exists only as placeholder handle

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        $this->assertTrue($aggregate->exists());
        $this->assertEquals('uploads', $aggregate->basename());
        $this->assertEquals('uploads', $aggregate->relativePath());
        $this->assertStringContainsString('uploads', (string) $aggregate);
    }

    /**
     * Test contains() checks whether a given absolute real path lives inside
     * ANY of the underlying physical directory roots.
     */
    public function test_contains_checks_across_all_underlying_directories(): void
    {
        $dir1 = $this->fs1->directory('assets', create: true)->create();
        $dir2 = $this->fs2->directory('assets', create: true)->create();

        // Create a file strictly inside the second filesystem directory on disk
        $fileInDir2 = $dir2->file('logo.png', create: true)->write('png data');

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        // Absolute path of the file created inside dir2
        $absolutePathInDir2 = (string) $fileInDir2;

        // contains() should return true because $absolutePathInDir2 sits inside $dir2
        $this->assertTrue($aggregate->contains($absolutePathInDir2));

        // An absolute path outside both directories should return false
        $this->assertFalse($aggregate->contains('/etc/passwd'));
    }

    /**
     * Test delete() removes directories across all underlying filesystems.
     */
    public function test_delete_deletes_all_underlying_directories(): void
    {
        $dir1 = $this->fs1->directory('cache', create: true)->create();
        $dir2 = $this->fs2->directory('cache', create: true)->create();

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        $this->assertTrue($dir1->exists());
        $this->assertTrue($dir2->exists());

        $aggregate->delete(recursive: true);

        $this->assertFalse($dir1->exists());
        $this->assertFalse($dir2->exists());
    }

    /**
     * Test file() lookup handles create: false and create: true with real files.
     */
    public function test_file_lookup_with_real_filesystems(): void
    {
        $dir1 = $this->fs1->directory('config', create: true)->create();
        $dir2 = $this->fs2->directory('config', create: true)->create();

        // Place file only in fs2
        $dir2->file('app.json', create: true)->write('{"env":"test"}');

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        // Scenario 1: create: false on existing file
        $file = $aggregate->file('app.json', create: false);
        $this->assertInstanceOf(AggregateFile::class, $file);
        $this->assertTrue($file->exists());
        $this->assertEquals('{"env":"test"}', $file->read());

        // Scenario 2: create: false on missing file
        $this->assertNull($aggregate->file('nonexistent.json', create: false));

        // Scenario 3: create: true returns handle ready for writing in primary root
        $newFile = $aggregate->file('new.txt', create: true);
        $this->assertInstanceOf(AggregateFile::class, $newFile);
        $newFile->write('hello');
        $this->assertTrue($this->fs1->file('config/new.txt')->exists());
    }

    /**
     * Test directory() lookup across real directory roots.
     */
    public function test_directory_lookup_with_real_filesystems(): void
    {
        $dir1 = $this->fs1->directory('templates', create: true)->create();
        $dir2 = $this->fs2->directory('templates', create: true)->create();

        // Create nested subfolder in fs2
        $dir2->directory('emails', create: true)->create();

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        // Existing subdirectory
        $sub = $aggregate->directory('emails', create: false);
        $this->assertInstanceOf(AggregateDirectory::class, $sub);

        // Missing subdirectory
        $this->assertNull($aggregate->directory('missing', create: false));

        // New subdirectory creation
        $newSub = $aggregate->directory('pages', create: true);
        $this->assertInstanceOf(AggregateDirectory::class, $newSub);
        $newSub->create();
        $this->assertTrue($this->fs1->directory('templates/pages')->exists());
    }

    /**
     * Test files() aggregates and groups files across physical roots by relative path.
     */
    public function test_files_groups_children_across_physical_directories(): void
    {
        $dir1 = $this->fs1->directory('theme', create: true)->create();
        $dir2 = $this->fs2->directory('theme', create: true)->create();

        // fs1 (override layer): header.twig
        $dir1->file('header.twig', create: true)->write('Custom Header');

        // fs2 (base layer): header.twig & footer.twig
        $dir2->file('header.twig', create: true)->write('Default Header');
        $dir2->file('footer.twig', create: true)->write('Default Footer');

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        $files = $aggregate->files('*.twig');

        $this->assertCount(2, $files);

        // Find items by matching relative path in 0-indexed list
        $headerFile = current(array_filter($files, fn(AggregateFile $f) => $f->filename() === 'header.twig'));
        $footerFile = current(array_filter($files, fn(AggregateFile $f) => $f->filename() === 'footer.twig'));

        $this->assertInstanceOf(AggregateFile::class, $headerFile);
        $this->assertInstanceOf(AggregateFile::class, $footerFile);

        // header.twig should read from fs1 (highest precedence)
        $this->assertEquals('Custom Header', $headerFile->read());

        // footer.twig should read from fs2
        $this->assertEquals('Default Footer', $footerFile->read());
    }

    /**
     * Test directories() aggregates subdirectories across physical roots.
     */
    public function test_directories_groups_subdirectories_across_physical_roots(): void
    {
        $dir1 = $this->fs1->directory('views', create: true)->create();
        $dir2 = $this->fs2->directory('views', create: true)->create();

        $dir1->directory('admin', create: true)->create();
        $dir2->directory('admin', create: true)->create();
        $dir2->directory('public', create: true)->create();

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        $subdirs = $aggregate->directories();

        $this->assertCount(2, $subdirs);

        $basenames = array_map(fn(AggregateDirectory $d) => $d->basename(), $subdirs);
        $this->assertContains('admin', $basenames);
        $this->assertContains('public', $basenames);
    }

    /**
     * Test globFile() and globDirectory() find first matching item or return null.
     */
    public function test_glob_helpers_with_real_filesystems(): void
    {
        $dir1 = $this->fs1->directory('docs', create: true)->create();
        $dir2 = $this->fs2->directory('docs', create: true)->create();

        $dir2->file('README.md', create: true)->write('# Readme');
        $dir2->directory('api-v1', create: true)->create();

        $aggregate = new AggregateDirectory($this->aggregateFs, $dir1, $dir2);

        // Glob File
        $readme = $aggregate->globFile('READ*');
        $this->assertInstanceOf(AggregateFile::class, $readme);
        $this->assertEquals('# Readme', $readme->read());
        $this->assertNull($aggregate->globFile('MISSING*'));

        // Glob Directory
        $apiDir = $aggregate->globDirectory('api-*');
        $this->assertInstanceOf(AggregateDirectory::class, $apiDir);
        $this->assertEquals('api-v1', $apiDir->basename());
        $this->assertNull($aggregate->globDirectory('missing-*'));
    }

}
