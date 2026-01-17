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
 * Functional tests for File class.
 */
class FileTest extends TestCase
{

    private string $testRoot;

    protected function setUp(): void
    {
        // Create temp directory for testing
        $this->testRoot = sys_get_temp_dir() . '/' . 'smolfs_file_test_' . uniqid() . '/';
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

    public function test_write_creates_file_with_content(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $file->write('Hello, World!');

        $this->assertTrue($file->exists());
        $this->assertEquals('Hello, World!', $file->read());
        $this->assertTrue(is_file($this->testRoot . 'test.txt'));
        $this->assertEquals('Hello, World!', file_get_contents($this->testRoot . 'test.txt'));
    }

    public function test_write_overwrites_existing_content(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $file->write('First content');
        $file->write('Second content');

        $this->assertEquals('Second content', $file->read());
        $this->assertEquals('Second content', file_get_contents($this->testRoot . 'test.txt'));
    }

    public function test_write_creates_nested_directories(): void
    {
        $file = new File($this->testRoot . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'file.txt', $this->testRoot);
        $file->write('Content');

        $this->assertTrue($file->exists());
        $this->assertEquals('Content', $file->read());
        $this->assertTrue(is_file($this->testRoot . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'file.txt'));
        $this->assertEquals('Content', file_get_contents($this->testRoot . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'file.txt'));
    }

    public function test_write_returns_self_for_chaining(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $result = $file->write('Content');

        $this->assertSame($file, $result);
    }

    public function test_write_with_empty_string(): void
    {
        $file = new File($this->testRoot . 'empty.txt', $this->testRoot);
        $file->write('');

        $this->assertTrue($file->exists());
        $this->assertEquals('', $file->read());
    }

    public function test_write_with_large_content(): void
    {
        $file = new File($this->testRoot . 'large.txt', $this->testRoot);
        $largeContent = str_repeat('x', 1000000); // 1MB
        $file->write($largeContent);

        $this->assertEquals($largeContent, $file->read());
    }

    public function test_write_with_special_characters(): void
    {
        $file = new File($this->testRoot . 'special.txt', $this->testRoot);
        $content = "Line 1\nLine 2\r\nLine 3\t\tTabbed";
        $file->write($content);

        $this->assertEquals($content, $file->read());
    }

    public function test_write_with_unicode(): void
    {
        $file = new File($this->testRoot . 'unicode.txt', $this->testRoot);
        $content = "Hello 世界 🌍 مرحبا";
        $file->write($content);

        $this->assertEquals($content, $file->read());
    }

    public function test_read_nonexistent_file_returns_false(): void
    {
        $file = new File($this->testRoot . 'nonexistent.txt', $this->testRoot);
        $this->assertFalse($file->read());
    }

    public function test_read_existing_file(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $content = 'Test content';
        $file->write($content);

        $this->assertEquals($content, $file->read());
    }

    public function test_append_to_nonexistent_file_creates_it(): void
    {
        $file = new File($this->testRoot . 'append_test.txt', $this->testRoot);
        $file->append('First line');

        $this->assertTrue($file->exists());
        $this->assertEquals('First line', $file->read());
    }

    public function test_append_to_existing_file(): void
    {
        $file = new File($this->testRoot . 'append_test.txt', $this->testRoot);
        $file->write('First ');
        $file->append('Second');

        $this->assertEquals('First Second', $file->read());
    }

    public function test_append_multiple_times(): void
    {
        $file = new File($this->testRoot . 'append_test.txt', $this->testRoot);
        $file->append('A');
        $file->append('B');
        $file->append('C');

        $this->assertEquals('ABC', $file->read());
        $this->assertEquals('ABC', file_get_contents($this->testRoot . 'append_test.txt'));
    }

    public function test_append_returns_self_for_chaining(): void
    {
        $file = new File($this->testRoot . 'append_test.txt', $this->testRoot);
        $result = $file->append('Content');

        $this->assertSame($file, $result);
    }

    public function test_append_with_empty_string(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $file->write('Original');
        $file->append('');

        $this->assertEquals('Original', $file->read());
    }

    public function test_append_creates_nested_directories(): void
    {
        $file = new File($this->testRoot . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'append.txt', $this->testRoot);
        $file->append('Content');

        $this->assertTrue($file->exists());
        $this->assertEquals('Content', $file->read());
    }

    public function test_delete_existing_file(): void
    {
        $file = new File($this->testRoot . 'to_delete.txt', $this->testRoot);
        $file->write('Content');

        $this->assertTrue($file->exists());
        $file->delete();
        $this->assertFalse($file->exists());
    }

    public function test_delete_nonexistent_file_succeeds(): void
    {
        $file = new File($this->testRoot . 'nonexistent.txt', $this->testRoot);

        $this->assertFalse($file->exists());
        // Should not throw an exception
        $file->delete();
        $this->assertFalse($file->exists());
    }

    public function test_delete_returns_self_for_chaining(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $file->write('Content');
        $result = $file->delete();

        $this->assertSame($file, $result);
    }

    public function test_exists_returns_true_for_existing_file(): void
    {
        $file = new File($this->testRoot . 'exists.txt', $this->testRoot);
        $file->write('Content');

        $this->assertTrue($file->exists());
    }

    public function test_exists_returns_false_for_nonexistent_file(): void
    {
        $file = new File($this->testRoot . 'nonexistent.txt', $this->testRoot);

        $this->assertFalse($file->exists());
    }

    public function test_exists_returns_false_for_directory(): void
    {
        $file = new File($this->testRoot . 'subdir', $this->testRoot);

        $this->assertFalse($file->exists());
    }

    public function test_size_returns_correct_size(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $content = 'Hello, World!';
        $file->write($content);

        $this->assertEquals(strlen($content), $file->size());
    }

    public function test_size_returns_zero_for_empty_file(): void
    {
        $file = new File($this->testRoot . 'empty.txt', $this->testRoot);
        $file->write('');

        $this->assertEquals(0, $file->size());
    }

    public function test_size_returns_false_for_nonexistent_file(): void
    {
        $file = new File($this->testRoot . 'nonexistent.txt', $this->testRoot);

        $this->assertFalse($file->size());
    }

    public function test_filename_returns_basename(): void
    {
        $file = new File($this->testRoot . 'subdir' . DIRECTORY_SEPARATOR . 'myfile.txt', $this->testRoot);

        $this->assertEquals('myfile.txt', $file->filename());
    }

    public function test_filename_for_nested_path(): void
    {
        $file = new File($this->testRoot . 'deep' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'document.pdf', $this->testRoot);

        $this->assertEquals('document.pdf', $file->filename());
    }

    public function test_filename_for_file_without_extension(): void
    {
        $file = new File($this->testRoot . 'README', $this->testRoot);

        $this->assertEquals('README', $file->filename());
    }

    public function test_extension_returns_lowercase_extension(): void
    {
        $file = new File($this->testRoot . 'test.TXT', $this->testRoot);

        $this->assertEquals('txt', $file->extension());
    }

    public function test_extension_with_mixed_case(): void
    {
        $file = new File($this->testRoot . 'archive.TaR.Gz', $this->testRoot);

        $this->assertEquals('gz', $file->extension());
    }

    public function test_extension_returns_empty_for_no_extension(): void
    {
        $file = new File($this->testRoot . 'README', $this->testRoot);

        $this->assertEquals('', $file->extension());
    }

    public function test_extension_with_dot_file(): void
    {
        $file = new File($this->testRoot . 'Makefile', $this->testRoot);

        $this->assertEquals('', $file->extension());
    }

    public function test_method_chaining_write_delete(): void
    {
        $file = new File($this->testRoot . 'chain_test.txt', $this->testRoot);
        $result = $file->write('Content')->delete();

        $this->assertSame($file, $result);
        $this->assertFalse($file->exists());
    }

    public function test_method_chaining_write_append_read(): void
    {
        $file = new File($this->testRoot . 'chain_test.txt', $this->testRoot);
        $file->write('Hello')->append(' ')->append('World');

        $this->assertEquals('Hello World', $file->read());
    }

    public function test_method_chaining_multiple_writes(): void
    {
        $file = new File($this->testRoot . 'chain_test.txt', $this->testRoot);
        $result = $file->write('First')->write('Second')->write('Third');

        $this->assertSame($file, $result);
        $this->assertEquals('Third', $file->read());
    }

    public function test_consecutive_reads_return_same_content(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $content = 'Stable content';
        $file->write($content);

        $this->assertEquals($content, $file->read());
        $this->assertEquals($content, $file->read());
        $this->assertEquals($content, $file->read());
    }

    public function test_write_after_delete(): void
    {
        $file = new File($this->testRoot . 'test.txt', $this->testRoot);
        $file->write('First');
        $file->delete();
        $file->write('Second');

        $this->assertTrue($file->exists());
        $this->assertEquals('Second', $file->read());
    }

    public function test_appendLine_to_nonexistent_file(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);
        $file->appendLine('first line');

        $this->assertEquals('first line', $file->read());
    }

    public function test_appendLine_to_empty_file(): void
    {
        touch($this->testRoot . 'log.txt');
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);
        $file->appendLine('first line');

        $this->assertEquals('first line', $file->read());
    }

    public function test_appendLine_twice_creates_two_lines(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('first line');
        $file->appendLine('second line');

        $this->assertEquals("first line\nsecond line", $file->read());
    }

    public function test_appendLine_to_file_ending_with_newline(): void
    {
        file_put_contents($this->testRoot . 'log.txt', "existing line\n");
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('new line');

        $this->assertEquals("existing line\nnew line", $file->read());
    }

    public function test_appendLine_to_file_not_ending_with_newline(): void
    {
        file_put_contents($this->testRoot . 'log.txt', 'existing line');
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('new line');

        $this->assertEquals("existing line\nnew line", $file->read());
    }

    public function test_appendLine_multiple_times(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);
        $file->appendLine('line 1');
        $file->appendLine('line 2');
        $file->appendLine('line 3');

        $this->assertEquals("line 1\nline 2\nline 3", $file->read());
    }

    public function test_appendLine_no_trailing_newline_after_final_line(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('only line');

        $content = $file->read();
        $this->assertStringEndsNotWith("\n", $content);
    }

    public function test_appendLine_with_empty_strings(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('first');
        $file->appendLine('');
        $file->appendLine('');
        $file->appendLine('third');

        $this->assertEquals("first\nthird", $file->read());
    }

    public function test_appendLine_mixed_with_append(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('line 1');
        $file->append(' inline addition');
        $file->appendLine('line 2');

        // The inline addition doesn't end with newline, so appendLine adds one
        $this->assertEquals("line 1 inline addition\nline 2", $file->read());
    }

    public function test_appendLine_creates_parent_directories(): void
    {
        $file = new File($this->testRoot . 'nested' . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'log.txt', $this->testRoot);

        $file->appendLine('test line');

        $this->assertTrue(is_dir($this->testRoot . 'nested' . DIRECTORY_SEPARATOR . 'deep'));
        $this->assertEquals('test line', $file->read());
    }

    public function test_appendLine_with_special_characters(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);

        $file->appendLine('line with "quotes" and \'apostrophes\'');
        $file->appendLine('line with $special @characters #here');

        $expected = "line with \"quotes\" and 'apostrophes'\nline with \$special @characters #here";
        $this->assertEquals($expected, $file->read());
    }

    public function test_appendLine_returns_self_for_chaining(): void
    {
        $file = new File($this->testRoot . 'log.txt', $this->testRoot);
        $result = $file->appendLine('first')->appendLine('second');

        $this->assertSame($file, $result);
        $this->assertEquals("first\nsecond", $file->read());
    }

    public function test_relativePath_returns_correct_path(): void
    {
        $file = new File($this->testRoot . 'subdir/file.txt', $this->testRoot);

        $this->assertEquals('subdir/file.txt', $file->relativePath());
    }

}
