<?php

namespace Joby\Smol\Filesystem;

use PHPUnit\Framework\TestCase;

class FilesystemTest extends TestCase
{

    private string $testRoot;

    protected function setUp(): void
    {
        // Create temp directory for testing
        $this->testRoot = sys_get_temp_dir() . '/smolfs_test_' . uniqid();
        mkdir($this->testRoot);
        mkdir($this->testRoot . '/subdir');
        mkdir($this->testRoot . '/subdir/nested');
        touch($this->testRoot . '/file.txt');
        touch($this->testRoot . '/subdir/file2.txt');
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

    public function test_constructor_accepts_valid_directory(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->assertStringEndsWith('/', $fs->root);
    }

    public function test_constructor_normalizes_root_to_forward_slashes(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->assertStringNotContainsString('\\', $fs->root);
    }

    public function test_constructor_throws_on_nonexistent_directory(): void
    {
        $this->expectException(FilesystemException::class);
        new Filesystem('/this/does/not/exist');
    }

    public function test_constructor_throws_on_file_instead_of_directory(): void
    {
        $this->expectException(FilesystemException::class);
        new Filesystem($this->testRoot . '/file.txt');
    }

    public function test_file_returns_file_object_for_existing_file(): void
    {
        $fs = new Filesystem($this->testRoot);
        $file = $fs->file('file.txt');
        $this->assertInstanceOf(File::class, $file);
        $this->assertStringEndsWith('/file.txt', $file->path);
    }

    public function test_file_returns_null_for_nonexistent_file_when_create_false(): void
    {
        $fs = new Filesystem($this->testRoot);
        $file = $fs->file('does-not-exist.txt', false);
        $this->assertNull($file);
    }

    public function test_file_returns_file_object_for_nonexistent_file_when_create_true(): void
    {
        $fs = new Filesystem($this->testRoot);
        $file = $fs->file('new-file.txt', true);
        $this->assertInstanceOf(File::class, $file);
        $this->assertFalse($file->exists());
    }

    public function test_file_throws_on_directory(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->expectException(FilesystemException::class);
        $fs->file('subdir');
    }

    public function test_file_throws_on_traversal_attempt(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->expectException(FilesystemSecurityException::class);
        $fs->file('../outside.txt');
    }

    public function test_file_handles_nested_paths(): void
    {
        $fs = new Filesystem($this->testRoot);
        $file = $fs->file('subdir/file2.txt');
        $this->assertInstanceOf(File::class, $file);
    }

    public function test_directory_returns_directory_object_for_existing_directory(): void
    {
        $fs = new Filesystem($this->testRoot);
        $dir = $fs->directory('subdir');
        $this->assertInstanceOf(Directory::class, $dir);
    }

    public function test_directory_returns_null_for_nonexistent_when_create_false(): void
    {
        $fs = new Filesystem($this->testRoot);
        $dir = $fs->directory('does-not-exist', false);
        $this->assertNull($dir);
    }

    public function test_directory_returns_directory_object_for_nonexistent_when_create_true(): void
    {
        $fs = new Filesystem($this->testRoot);
        $dir = $fs->directory('new-dir', true);
        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertFalse($dir->exists());
    }

    public function test_directory_throws_on_file(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->expectException(FilesystemException::class);
        $fs->directory('file.txt');
    }

    public function test_directory_throws_on_traversal_attempt(): void
    {
        $fs = new Filesystem($this->testRoot);
        $this->expectException(FilesystemSecurityException::class);
        $fs->directory('../outside');
    }

    public function test_copy_copies_file_successfully(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');

        $fs->copy('source.txt', 'dest.txt', false);

        $this->assertFileExists($this->testRoot . '/dest.txt');
        $this->assertEquals('test content', file_get_contents($this->testRoot . '/dest.txt'));
    }

    public function test_copy_accepts_file_objects(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');

        $source = $fs->file('source.txt');
        $dest = $fs->file('dest.txt', true);
        $fs->copy($source, $dest, false);

        $this->assertFileExists($this->testRoot . '/dest.txt');
    }

    public function test_copy_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test');

        $fs->copy('source.txt', 'deep/nested/dest.txt', false);

        $this->assertFileExists($this->testRoot . '/deep/nested/dest.txt');
    }

    public function test_copy_throws_when_overwrite_false_and_dest_exists(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'source');
        file_put_contents($this->testRoot . '/dest.txt', 'dest');

        $this->expectException(FilesystemException::class);
        $fs->copy('source.txt', 'dest.txt', false);
    }

    public function test_copy_overwrites_when_allow_overwrite_true(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'new content');
        file_put_contents($this->testRoot . '/dest.txt', 'old content');

        $fs->copy('source.txt', 'dest.txt', true);

        $this->assertEquals('new content', file_get_contents($this->testRoot . '/dest.txt'));
    }

    public function test_copy_throws_when_source_does_not_exist(): void
    {
        $fs = new Filesystem($this->testRoot);

        $this->expectException(FilesystemException::class);
        $fs->copy('nonexistent.txt', 'dest.txt', false);
    }

    public function test_copy_throws_on_traversal_in_source(): void
    {
        $fs = new Filesystem($this->testRoot);

        $this->expectException(FilesystemSecurityException::class);
        $fs->copy('../outside.txt', 'dest.txt', false);
    }

    public function test_copy_throws_on_traversal_in_destination(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test');

        $this->expectException(FilesystemSecurityException::class);
        $fs->copy('source.txt', '../outside.txt', false);
    }

    public function test_move_moves_file_successfully(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');

        $fs->move('source.txt', 'dest.txt', false);

        $this->assertFileDoesNotExist($this->testRoot . '/source.txt');
        $this->assertFileExists($this->testRoot . '/dest.txt');
        $this->assertEquals('test content', file_get_contents($this->testRoot . '/dest.txt'));
    }

    public function test_move_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test');

        $fs->move('source.txt', 'deep/nested/dest.txt', false);

        $this->assertFileExists($this->testRoot . '/deep/nested/dest.txt');
    }

    public function test_files_returns_all_files_in_root(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/file2.txt');

        $files = $fs->files();

        $this->assertCount(2, $files);
        $this->assertContainsOnlyInstancesOf(File::class, $files);
    }

    public function test_files_excludes_directories(): void
    {
        $fs = new Filesystem($this->testRoot);

        $files = $fs->files();

        foreach ($files as $file) {
            $this->assertStringNotContainsString('subdir', $file->path);
        }
    }

    public function test_files_respects_glob_pattern(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/test.txt');
        touch($this->testRoot . '/test.md');

        $files = $fs->files('*.txt');

        $this->assertGreaterThan(0, count($files));
        foreach ($files as $file) {
            $this->assertStringEndsWith('.txt', $file->path);
        }
    }

    public function test_files_respects_filter_function(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/small.txt');
        file_put_contents($this->testRoot . '/large.txt', str_repeat('x', 1000));

        $files = $fs->files(null, fn(File $f) => $f->size() > 100);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('large.txt', $files[0]->path);
    }

    public function test_directories_returns_all_directories_in_root(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/another');

        $dirs = $fs->directories();

        $this->assertCount(2, $dirs);
        $this->assertContainsOnlyInstancesOf(Directory::class, $dirs);
    }

    public function test_directories_excludes_files(): void
    {
        $fs = new Filesystem($this->testRoot);

        $dirs = $fs->directories();

        foreach ($dirs as $dir) {
            $this->assertStringNotContainsString('file.txt', $dir->path);
        }
    }

    public function test_directories_respects_glob_pattern(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/test-dir');
        mkdir($this->testRoot . '/prod-dir');

        $dirs = $fs->directories('test-*');

        $this->assertCount(1, $dirs);
        $this->assertStringContainsString('test-dir', $dirs[0]->path);
    }

    public function test_directories_respects_filter_function(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/alpha');
        mkdir($this->testRoot . '/beta');

        $dirs = $fs->directories(null, fn(Directory $d) => str_contains($d->path, 'alpha'));

        $this->assertCount(1, $dirs);
    }

    public function test_globFile_returns_first_matching_file(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/test1.txt');
        touch($this->testRoot . '/test2.txt');
        touch($this->testRoot . '/other.md');

        $file = $fs->globFile('*.txt');

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringEndsWith('.txt', $file->path);
    }

    public function test_globFile_returns_null_when_no_match(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/file.txt');

        $file = $fs->globFile('*.md');

        $this->assertNull($file);
    }

    public function test_globFile_supports_glob_brace_syntax(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/file.txt');
        touch($this->testRoot . '/file.md');
        touch($this->testRoot . '/file.log');

        $file = $fs->globFile('*.{md,log}');

        $this->assertInstanceOf(File::class, $file);
        $this->assertTrue(
            str_ends_with($file->path, '.md') || str_ends_with($file->path, '.log')
        );
    }

    public function test_globFile_respects_filter_function(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/small.txt');
        file_put_contents($this->testRoot . '/large.txt', str_repeat('x', 1000));

        $file = $fs->globFile('*.txt', fn(File $f) => $f->size() > 100);

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringContainsString('large.txt', $file->path);
    }

    public function test_globFile_applies_filter_after_glob_match(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/small.txt');
        file_put_contents($this->testRoot . '/large.txt', str_repeat('x', 1000));
        touch($this->testRoot . '/other.md');

        $file = $fs->globFile('*.txt', fn(File $f) => $f->size() > 100);

        $this->assertInstanceOf(File::class, $file);
        $this->assertStringContainsString('large.txt', $file->path);
    }

    public function test_globFile_returns_null_when_filter_excludes_all(): void
    {
        $fs = new Filesystem($this->testRoot);
        touch($this->testRoot . '/file1.txt');
        touch($this->testRoot . '/file2.txt');

        $file = $fs->globFile('*.txt', fn(File $f) => false);

        $this->assertNull($file);
    }

    public function test_globDirectory_returns_first_matching_directory(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/test1');
        mkdir($this->testRoot . '/test2');
        mkdir($this->testRoot . '/other');

        $dir = $fs->globDirectory('test*');

        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertStringContainsString('test', $dir->path);
    }

    public function test_globDirectory_returns_null_when_no_match(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/dir');

        $dir = $fs->globDirectory('nomatch*');

        $this->assertNull($dir);
    }

    public function test_globDirectory_supports_glob_brace_syntax(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/prod-env');
        mkdir($this->testRoot . '/test-env');
        mkdir($this->testRoot . '/other');

        $dir = $fs->globDirectory('{prod,test}-*');

        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertTrue(
            str_contains($dir->path, 'prod-') || str_contains($dir->path, 'test-')
        );
    }

    public function test_globDirectory_respects_filter_function(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/alpha');
        mkdir($this->testRoot . '/beta');

        $dir = $fs->globDirectory('*', fn(Directory $d) => str_contains($d->path, 'beta'));

        $this->assertInstanceOf(Directory::class, $dir);
        $this->assertStringContainsString('beta', $dir->path);
    }

    public function test_globDirectory_returns_null_when_filter_excludes_all(): void
    {
        $fs = new Filesystem($this->testRoot);
        mkdir($this->testRoot . '/dir1');
        mkdir($this->testRoot . '/dir2');

        $dir = $fs->globDirectory('*', fn(Directory $d) => false);

        $this->assertNull($dir);
    }

    public function test_filesystem_creates_new_filesystem_at_subpath(): void
    {
        $fs = new Filesystem($this->testRoot);

        $subfs = $fs->filesystem('subdir');

        $this->assertInstanceOf(Filesystem::class, $subfs);
        $this->assertStringContainsString('subdir', $subfs->root);
    }

    public function test_filesystem_creates_directory_if_not_exists(): void
    {
        $fs = new Filesystem($this->testRoot);

        $subfs = $fs->filesystem('newdir');

        $this->assertDirectoryExists($this->testRoot . '/newdir');
    }

    public function test_filesystem_throws_on_traversal_attempt(): void
    {
        $fs = new Filesystem($this->testRoot);

        $this->expectException(FilesystemSecurityException::class);
        $fs->filesystem('../outside');
    }

    public function test_filesystem_handles_nested_paths(): void
    {
        $fs = new Filesystem($this->testRoot);

        $subfs = $fs->filesystem('deep/nested/path');

        $this->assertDirectoryExists($this->testRoot . '/deep/nested/path');
    }

    public function test_copyOut_copies_file_from_filesystem_to_outside(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';

        try {
            $fs->copyOut('source.txt', $outside, false);

            $this->assertFileExists($outside);
            $this->assertEquals('test content', file_get_contents($outside));
            $this->assertFileExists($this->testRoot . '/source.txt'); // Source still exists
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyOut_works_with_file_objects(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';

        try {
            $file = $fs->file('source.txt');
            $fs->copyOut($file, $outside, false);

            $this->assertFileExists($outside);
            $this->assertEquals('test content', file_get_contents($outside));
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyOut_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test');
        $outside = sys_get_temp_dir() . '/deep-' . uniqid() . '/nested/file.txt';

        try {
            $fs->copyOut('source.txt', $outside, false);

            $this->assertFileExists($outside);
        }
        finally {
            if (file_exists($outside)) {
                unlink($outside);
                rmdir(dirname($outside));
                rmdir(dirname(dirname($outside)));
            }
        }
    }

    public function test_copyOut_throws_when_source_does_not_exist(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';

        $this->expectException(FilesystemException::class);
        $fs->copyOut('nonexistent.txt', $outside, false);
    }

    public function test_copyOut_throws_when_destination_exists_and_no_overwrite(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'source');
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'existing');

        try {
            $this->expectException(FilesystemException::class);
            $fs->copyOut('source.txt', $outside, false);
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyOut_overwrites_when_allowed(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'new content');
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'old content');

        try {
            $fs->copyOut('source.txt', $outside, true);

            $this->assertEquals('new content', file_get_contents($outside));
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyOut_throws_on_source_traversal(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';

        $this->expectException(FilesystemSecurityException::class);
        $fs->copyOut('../../outside.txt', $outside, false);
    }

    public function test_moveOut_moves_file_from_filesystem_to_outside(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test content');
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';

        try {
            $fs->moveOut('source.txt', $outside, false);

            $this->assertFileExists($outside);
            $this->assertEquals('test content', file_get_contents($outside));
            $this->assertFileDoesNotExist($this->testRoot . '/source.txt');
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_moveOut_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        file_put_contents($this->testRoot . '/source.txt', 'test');
        $outside = sys_get_temp_dir() . '/deep-' . uniqid() . '/nested/file.txt';

        try {
            $fs->moveOut('source.txt', $outside, false);

            $this->assertFileExists($outside);
            $this->assertFileDoesNotExist($this->testRoot . '/source.txt');
        }
        finally {
            if (file_exists($outside)) {
                unlink($outside);
                rmdir(dirname($outside));
                rmdir(dirname(dirname($outside)));
            }
        }
    }

    public function test_copyIn_copies_file_from_outside_to_filesystem(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test content');

        try {
            $fs->copyIn($outside, 'dest.txt', false);

            $this->assertFileExists($this->testRoot . '/dest.txt');
            $this->assertEquals('test content', file_get_contents($this->testRoot . '/dest.txt'));
            $this->assertFileExists($outside); // Source still exists
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyIn_works_with_file_objects(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test content');

        try {
            $dest = $fs->file('dest.txt', create: true);
            $fs->copyIn($outside, $dest, false);

            $this->assertFileExists($this->testRoot . '/dest.txt');
            $this->assertEquals('test content', file_get_contents($this->testRoot . '/dest.txt'));
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyIn_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test');

        try {
            $fs->copyIn($outside, 'deep/nested/dest.txt', false);

            $this->assertFileExists($this->testRoot . '/deep/nested/dest.txt');
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyIn_throws_when_source_does_not_exist(): void
    {
        $fs = new Filesystem($this->testRoot);

        $this->expectException(FilesystemException::class);
        $fs->copyIn('/nonexistent/file.txt', 'dest.txt', false);
    }

    public function test_copyIn_throws_when_destination_exists_and_no_overwrite(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'source');
        file_put_contents($this->testRoot . '/dest.txt', 'existing');

        try {
            $this->expectException(FilesystemException::class);
            $fs->copyIn($outside, 'dest.txt', false);
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyIn_overwrites_when_allowed(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'new content');
        file_put_contents($this->testRoot . '/dest.txt', 'old content');

        try {
            $fs->copyIn($outside, 'dest.txt', true);

            $this->assertEquals('new content', file_get_contents($this->testRoot . '/dest.txt'));
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_copyIn_throws_on_destination_traversal(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test');

        try {
            $this->expectException(FilesystemSecurityException::class);
            $fs->copyIn($outside, '../../escape.txt', false);
        }
        finally {
            if (file_exists($outside))
                unlink($outside);
        }
    }

    public function test_moveIn_moves_file_from_outside_to_filesystem(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test content');

        $fs->moveIn($outside, 'dest.txt', false);

        $this->assertFileExists($this->testRoot . '/dest.txt');
        $this->assertEquals('test content', file_get_contents($this->testRoot . '/dest.txt'));
        $this->assertFileDoesNotExist($outside);
    }

    public function test_moveIn_creates_parent_directories(): void
    {
        $fs = new Filesystem($this->testRoot);
        $outside = sys_get_temp_dir() . '/outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'test');

        $fs->moveIn($outside, 'deep/nested/dest.txt', false);

        $this->assertFileExists($this->testRoot . '/deep/nested/dest.txt');
        $this->assertFileDoesNotExist($outside);
    }

}
