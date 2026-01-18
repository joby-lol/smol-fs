<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use PHPUnit\Framework\TestCase;

/**
 * Functional tests for Directory class.
 */
class DirectoryTest extends TestCase
{

    private string $testRoot;

    protected function setUp(): void
    {
        // Create temp directory for testing
        $this->testRoot = sys_get_temp_dir() . '/' . 'smolfs_directory_test_' . uniqid() . '/';
        mkdir($this->testRoot);
        mkdir($this->testRoot . 'subdir');
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testRoot)) {
            $this->deleteDirectory($this->testRoot);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_constructor_stores_path_and_root(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->assertEquals($this->testRoot . 'subdir', $dir->path);
        $this->assertEquals($this->testRoot, $dir->root);
    }

    public function test_create_creates_directory(): void
    {
        $dir = new Directory($this->testRoot . 'newdir', $this->testRoot);

        $dir->create();

        $this->assertDirectoryExists($this->testRoot . 'newdir');
    }

    public function test_create_creates_parent_directories(): void
    {
        $dir = new Directory($this->testRoot . 'deep/nested/dir', $this->testRoot);

        $dir->create();

        $this->assertDirectoryExists($this->testRoot . 'deep/nested/dir');
    }

    public function test_create_returns_self(): void
    {
        $dir = new Directory($this->testRoot . 'newdir', $this->testRoot);

        $result = $dir->create();

        $this->assertSame($dir, $result);
    }

    public function test_create_succeeds_if_already_exists(): void
    {
        mkdir($this->testRoot . 'existing');
        $dir = new Directory($this->testRoot . 'existing', $this->testRoot);

        $dir->create();

        $this->assertDirectoryExists($this->testRoot . 'existing');
    }

    public function test_delete_removes_empty_directory(): void
    {
        mkdir($this->testRoot . 'todelete');
        $dir = new Directory($this->testRoot . 'todelete', $this->testRoot);

        $dir->delete();

        $this->assertDirectoryDoesNotExist($this->testRoot . 'todelete');
    }

    public function test_delete_returns_self(): void
    {
        mkdir($this->testRoot . 'todelete');
        $dir = new Directory($this->testRoot . 'todelete', $this->testRoot);

        $result = $dir->delete();

        $this->assertSame($dir, $result);
    }

    public function test_delete_throws_on_non_empty_directory_when_not_recursive(): void
    {
        mkdir($this->testRoot . 'nonempty');
        touch($this->testRoot . 'nonempty/file.txt');
        $dir = new Directory($this->testRoot . 'nonempty', $this->testRoot);

        $this->expectException(FilesystemException::class);
        $dir->delete(false);
    }

    public function test_delete_removes_contents_when_recursive(): void
    {
        mkdir($this->testRoot . 'parent');
        mkdir($this->testRoot . 'parent/child');
        touch($this->testRoot . 'parent/file.txt');
        touch($this->testRoot . 'parent/child/nested.txt');
        $dir = new Directory($this->testRoot . 'parent', $this->testRoot);

        $dir->delete(true);

        $this->assertDirectoryDoesNotExist($this->testRoot . 'parent');
    }

    public function test_file_returns_file_object_for_existing_file(): void
    {
        touch($this->testRoot . 'subdir/test.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->file('test.txt');

        $this->assertInstanceOf(File::class, $file);
    }

    public function test_file_returns_null_for_nonexistent_when_create_false(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->file('nonexistent.txt', false);

        $this->assertNull($file);
    }

    public function test_file_returns_file_object_for_nonexistent_when_create_true(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->file('new.txt', true);

        $this->assertInstanceOf(File::class, $file);
        $this->assertFalse($file->exists());
    }

    public function test_file_throws_on_directory(): void
    {
        mkdir($this->testRoot . 'subdir/nested');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->expectException(FilesystemException::class);
        $dir->file('nested');
    }

    public function test_file_throws_on_traversal(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->expectException(FilesystemSecurityException::class);
        $dir->file('../../outside.txt');
    }

    public function test_directory_returns_directory_object_for_existing(): void
    {
        mkdir($this->testRoot . 'subdir/nested');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $nested = $dir->directory('nested');

        $this->assertInstanceOf(Directory::class, $nested);
    }

    public function test_directory_returns_null_for_nonexistent_when_create_false(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $nested = $dir->directory('nonexistent', false);

        $this->assertNull($nested);
    }

    public function test_directory_returns_directory_object_for_nonexistent_when_create_true(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $nested = $dir->directory('new', true);

        $this->assertInstanceOf(Directory::class, $nested);
        $this->assertFalse($nested->exists());
    }

    public function test_directory_throws_on_file(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->expectException(FilesystemException::class);
        $dir->directory('file.txt');
    }

    public function test_directory_throws_on_traversal(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->expectException(FilesystemSecurityException::class);
        $dir->directory('../../outside');
    }

    public function test_files_returns_all_files(): void
    {
        touch($this->testRoot . 'subdir/file1.txt');
        touch($this->testRoot . 'subdir/file2.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files();

        $this->assertCount(2, $files);
        $this->assertContainsOnlyInstancesOf(File::class, $files);
    }

    public function test_files_excludes_directories(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        mkdir($this->testRoot . 'subdir/nested');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files();

        $this->assertCount(1, $files);
    }

    public function test_files_returns_empty_array_when_no_files(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files();

        $this->assertEmpty($files);
    }

    public function test_files_respects_glob_pattern(): void
    {
        touch($this->testRoot . 'subdir/test.txt');
        touch($this->testRoot . 'subdir/test.md');
        touch($this->testRoot . 'subdir/other.log');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files('*.txt');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.txt', $files[0]->path);
    }

    public function test_files_supports_glob_brace_syntax(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        touch($this->testRoot . 'subdir/file.md');
        touch($this->testRoot . 'subdir/file.log');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files('*.{txt,md}');

        $this->assertCount(2, $files);
    }

    public function test_files_respects_filter_function(): void
    {
        touch($this->testRoot . 'subdir/small.txt');
        file_put_contents($this->testRoot . 'subdir/large.txt', str_repeat('x', 1000));
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files(null, fn(File $f) => $f->size() > 100);

        $this->assertCount(1, $files);
    }

    public function test_files_applies_both_glob_and_filter(): void
    {
        touch($this->testRoot . 'subdir/small.txt');
        file_put_contents($this->testRoot . 'subdir/large.txt', str_repeat('x', 1000));
        touch($this->testRoot . 'subdir/file.md');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $files = $dir->files('*.txt', fn(File $f) => $f->size() > 100);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('large.txt', $files[0]->path);
    }

    public function test_directories_returns_all_directories(): void
    {
        mkdir($this->testRoot . 'subdir/dir1');
        mkdir($this->testRoot . 'subdir/dir2');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $dirs = $dir->directories();

        $this->assertCount(2, $dirs);
        $this->assertContainsOnlyInstancesOf(Directory::class, $dirs);
    }

    public function test_directories_excludes_files(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        mkdir($this->testRoot . 'subdir/nested');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $dirs = $dir->directories();

        $this->assertCount(1, $dirs);
    }

    public function test_directories_returns_empty_array_when_no_directories(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $dirs = $dir->directories();

        $this->assertEmpty($dirs);
    }

    public function test_directories_respects_glob_pattern(): void
    {
        mkdir($this->testRoot . 'subdir/test-dir');
        mkdir($this->testRoot . 'subdir/prod-dir');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $dirs = $dir->directories('test-*');

        $this->assertCount(1, $dirs);
    }

    public function test_directories_respects_filter_function(): void
    {
        mkdir($this->testRoot . 'subdir/alpha');
        mkdir($this->testRoot . 'subdir/beta');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $dirs = $dir->directories(null, fn(Directory $d) => str_contains($d->basename(), 'alpha'));

        $this->assertCount(1, $dirs);
    }

    public function test_globFile_returns_first_matching_file(): void
    {
        touch($this->testRoot . 'subdir/test1.txt');
        touch($this->testRoot . 'subdir/test2.txt');
        touch($this->testRoot . 'subdir/other.md');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.txt');

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringEndsWith('.txt', $file->path);
    }

    public function test_globFile_returns_null_when_no_match(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.md');

        $this->assertNull($file);
    }

    public function test_globFile_supports_glob_brace_syntax(): void
    {
        touch($this->testRoot . 'subdir/file.txt');
        touch($this->testRoot . 'subdir/file.md');
        touch($this->testRoot . 'subdir/file.log');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.{md,log}');

        $this->assertInstanceOf(File::class, $file);
        $this->assertTrue(
            str_ends_with($file->path, '.md') || str_ends_with($file->path, '.log')
        );
    }

    public function test_globFile_respects_filter_function(): void
    {
        touch($this->testRoot . 'subdir/small.txt');
        file_put_contents($this->testRoot . 'subdir/large.txt', str_repeat('x', 1000));
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.txt', fn(File $f) => $f->size() > 100);

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringContainsString('large.txt', $file->path);
    }

    public function test_globFile_applies_filter_after_glob_match(): void
    {
        touch($this->testRoot . 'subdir/small.txt');
        file_put_contents($this->testRoot . 'subdir/large.txt', str_repeat('x', 1000));
        touch($this->testRoot . 'subdir/other.md');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.txt', fn(File $f) => $f->size() > 100);

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringContainsString('large.txt', $file->path);
    }

    public function test_globFile_returns_null_when_filter_excludes_all(): void
    {
        touch($this->testRoot . 'subdir/file1.txt');
        touch($this->testRoot . 'subdir/file2.txt');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $file = $dir->globFile('*.txt', fn(File $f) => false);

        $this->assertNull($file);
    }

    public function test_globDirectory_returns_first_matching_directory(): void
    {
        mkdir($this->testRoot . 'subdir/test1');
        mkdir($this->testRoot . 'subdir/test2');
        mkdir($this->testRoot . 'subdir/other');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $result = $dir->globDirectory('test*');

        $this->assertInstanceOf(Directory::class, $result);
        $this->assertStringContainsString('test', $result->path);
    }

    public function test_globDirectory_returns_null_when_no_match(): void
    {
        mkdir($this->testRoot . 'subdir/dir');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $result = $dir->globDirectory('nomatch*');

        $this->assertNull($result);
    }

    public function test_globDirectory_supports_glob_brace_syntax(): void
    {
        mkdir($this->testRoot . 'subdir/prod-env');
        mkdir($this->testRoot . 'subdir/test-env');
        mkdir($this->testRoot . 'subdir/other');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $result = $dir->globDirectory('{prod,test}-*');

        $this->assertInstanceOf(Directory::class, $result);
        $this->assertTrue(
            str_contains($result->path, 'prod-') || str_contains($result->path, 'test-')
        );
    }

    public function test_globDirectory_respects_filter_function(): void
    {
        mkdir($this->testRoot . 'subdir/alpha');
        mkdir($this->testRoot . 'subdir/beta');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $result = $dir->globDirectory('*', fn(Directory $d) => str_contains($d->basename(), 'beta'));

        $this->assertInstanceOf(Directory::class, $result);
        $this->assertStringContainsString('beta', $result->path);
    }

    public function test_globDirectory_returns_null_when_filter_excludes_all(): void
    {
        mkdir($this->testRoot . 'subdir/dir1');
        mkdir($this->testRoot . 'subdir/dir2');
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $result = $dir->globDirectory('*', fn(Directory $d) => false);

        $this->assertNull($result);
    }

    public function test_modified_returns_datetime_for_existing_directory(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $modified = $dir->modified();

        $this->assertInstanceOf(\DateTime::class, $modified);
    }

    public function test_modified_returns_null_for_nonexistent_directory(): void
    {
        $dir = new Directory($this->testRoot . 'nonexistent', $this->testRoot);

        $modified = $dir->modified();

        $this->assertNull($modified);
    }

    public function test_exists_returns_true_for_existing_directory(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->assertTrue($dir->exists());
    }

    public function test_exists_returns_false_for_nonexistent_directory(): void
    {
        $dir = new Directory($this->testRoot . 'nonexistent', $this->testRoot);

        $this->assertFalse($dir->exists());
    }

    public function test_exists_returns_false_for_file(): void
    {
        touch($this->testRoot . 'file.txt');
        $dir = new Directory($this->testRoot . 'file.txt', $this->testRoot);

        $this->assertFalse($dir->exists());
    }

    public function test_basename_returns_directory_name(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->assertEquals('subdir', $dir->basename());
    }

    public function test_basename_returns_last_segment_of_path(): void
    {
        mkdir($this->testRoot . 'parent/child', 0777, true);
        $dir = new Directory($this->testRoot . 'parent/child', $this->testRoot);

        $this->assertEquals('child', $dir->basename());
    }

    public function test_relativePath_returns_path_relative_to_root(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->assertEquals('subdir', $dir->relativePath());
    }

    public function test_relativePath_returns_nested_path(): void
    {
        mkdir($this->testRoot . 'parent/child', 0777, true);
        $dir = new Directory($this->testRoot . 'parent/child', $this->testRoot);

        $this->assertEquals('parent/child', $dir->relativePath());
    }

    public function test_relativePath_returns_empty_string_for_root(): void
    {
        $dir = new Directory(rtrim($this->testRoot, '/'), $this->testRoot);

        $this->assertEquals('', $dir->relativePath());
    }

    public function test_toString_returns_full_path(): void
    {
        $dir = new Directory($this->testRoot . 'subdir', $this->testRoot);

        $this->assertEquals($this->testRoot . 'subdir', (string) $dir);
    }

}
