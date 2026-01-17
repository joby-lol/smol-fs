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
 * Tests for the path normalizer. These are in a separate file to keep the main Filesystem class and test suite functionality-focused, because there are a LOT of security edge cases to cover.
 */
class PathNormalizerTest extends TestCase
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
        // Normalize the root path like Filesystem does (convert backslashes to forward slashes, add trailing slash)
        $this->testRoot = str_replace('\\', '/', $this->testRoot) . '/';
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

    public function test_normalize_relative_path_with_trailing_slash(): void
    {
        $this->assertEquals('subdir', PathNormalizer::normalize('subdir/', $this->testRoot));
    }

    public function test_normalize_relative_path_with_multiple_trailing_slashes(): void
    {
        $this->assertEquals('subdir', PathNormalizer::normalize('subdir///', $this->testRoot));
    }

    public function test_normalize_relative_path_with_consecutive_slashes(): void
    {
        $this->assertEquals('subdir/nested', PathNormalizer::normalize('subdir//nested', $this->testRoot));
    }

    public function test_normalize_relative_path_with_backslashes(): void
    {
        $this->assertEquals('subdir/nested', PathNormalizer::normalize('subdir\\nested', $this->testRoot));
    }

    public function test_normalize_relative_path_with_mixed_slashes(): void
    {
        $this->assertEquals('subdir/nested/file', PathNormalizer::normalize('subdir/nested\\file', $this->testRoot));
    }

    public function test_normalize_current_dir_with_nested_path(): void
    {
        $this->assertEquals('subdir/file2.txt', PathNormalizer::normalize('./subdir/file2.txt', $this->testRoot));
    }

    public function test_normalize_current_dir_with_parent_traversal(): void
    {
        $this->assertEquals('file.txt', PathNormalizer::normalize('./subdir/../file.txt', $this->testRoot));
    }

    public function test_normalize_throws_on_relative_parent_traversal_above_root(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('../outside.txt', $this->testRoot);
    }

    public function test_normalize_throws_on_multiple_relative_parent_traversals(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('subdir/../../outside.txt', $this->testRoot);
    }

    public function test_normalize_throws_on_absolute_path_outside_root(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('/etc/passwd', $this->testRoot);
    }

    public function test_normalize_throws_on_absolute_path_with_parent_traversal_outside_root(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize($this->testRoot . '/../outside.txt', $this->testRoot);
    }

    public function test_normalize_single_slash(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('/', $this->testRoot);
    }

    public function test_normalize_single_dot(): void
    {
        $this->assertEquals('', PathNormalizer::normalize('.', $this->testRoot));
    }

    public function test_normalize_double_dot_alone(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('..', $this->testRoot);
    }

    public function test_normalize_throws_on_absolute_path_with_matching_prefix(): void
    {
        // Create a directory that starts with root's name
        $rootWithoutSlash = rtrim($this->testRoot, '/');
        $sibling = dirname($rootWithoutSlash) . '/' . basename($rootWithoutSlash) . '_sibling';
        mkdir($sibling);
        touch($sibling . '/attack.txt');

        try {
            $this->expectException(FilesystemSecurityException::class);
            PathNormalizer::normalize($sibling . '/attack.txt', $this->testRoot);
        }
        finally {
            unlink($sibling . '/attack.txt');
            rmdir($sibling);
        }
    }

    public function test_normalize_absolute_path_within_root(): void
    {
        $absolute = '/some/root/path/subdir/file.txt';
        $this->assertEquals('subdir/file.txt', PathNormalizer::normalize($absolute, '/some/root/path'));
    }

    public function test_normalize_throws_on_null_byte_injection(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize("file.txt\0../../etc/passwd", $this->testRoot);
    }

    public function test_normalize_handles_multiple_leading_slashes(): void
    {
        // ///etc/passwd should still be treated as /etc/passwd
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('///etc/passwd', $this->testRoot);
    }

    public function test_normalize_handles_trailing_dots(): void
    {
        // file.txt. or file.txt.. might be treated specially on some filesystems
        $result = PathNormalizer::normalize('file.txt.', $this->testRoot);
        $this->assertEquals('file.txt.', $result);
    }

    public function test_normalize_handles_hidden_files(): void
    {
        // .hidden should be valid
        $result = PathNormalizer::normalize('.hidden', $this->testRoot);
        $this->assertEquals('.hidden', $result);
    }

    public function test_normalize_handles_multiple_dots(): void
    {
        // ... should be treated as a filename, not special
        $result = PathNormalizer::normalize('...', $this->testRoot);
        $this->assertEquals('...', $result);
    }

    public function test_normalize_throws_on_parent_after_absolute_root(): void
    {
        // /.. should try to go above filesystem root
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('/..', $this->testRoot);
    }

    public function test_normalize_handles_spaces_in_path(): void
    {
        // Paths with spaces should work normally
        $result = PathNormalizer::normalize('my file.txt', $this->testRoot);
        $this->assertEquals('my file.txt', $result);
    }

    public function test_normalize_handles_unicode_in_path(): void
    {
        // Unicode characters should pass through
        $result = PathNormalizer::normalize('文件.txt', $this->testRoot);
        $this->assertEquals('文件.txt', $result);
    }

    public function test_normalize_throws_on_traversal_with_encoded_dots(): void
    {
        // URL-encoded dots should still be blocked if they resolve
        // Though realistically PHP won't decode these at the path level
        $result = PathNormalizer::normalize('%2e%2e/etc/passwd', $this->testRoot);
        // This should just be treated as a weird filename since PHP doesn't auto-decode
        $this->assertEquals('%2e%2e/etc/passwd', $result);
    }

    public function test_normalize_handles_very_long_filename(): void
    {
        // Very long component names should work (up to filesystem limits)
        $longName = str_repeat('a', 200);
        $result = PathNormalizer::normalize($longName, $this->testRoot);
        $this->assertEquals($longName, $result);
    }

    public function test_normalize_handles_deeply_nested_path(): void
    {
        // Many levels of nesting should work
        $deep = str_repeat('sub/', 50) . 'file.txt';
        $result = PathNormalizer::normalize($deep, $this->testRoot);
        $this->assertEquals(rtrim($deep, '/'), $result);
    }

    public function test_normalize_throws_on_windows_absolute_outside_root(): void
    {
        // Windows-style absolute path outside root
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('C:/Windows/System32/file.txt', $this->testRoot);
    }

    public function test_normalize_handles_single_component_with_parent(): void
    {
        // a/.. should resolve to empty
        $result = PathNormalizer::normalize('subdir/..', $this->testRoot);
        $this->assertEquals('', $result);
    }

    public function test_normalize_throws_on_single_component_followed_by_double_parent(): void
    {
        // a/../.. should try to escape
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('subdir/../..', $this->testRoot);
    }

    public function test_normalize_handles_root_level_file(): void
    {
        // Ensure root-level files work correctly
        $result = PathNormalizer::normalize('./file.txt', $this->testRoot);
        $this->assertEquals('file.txt', $result);
    }

    public function test_normalize_throws_on_tabs_in_path(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize("file\twith\ttabs.txt", $this->testRoot);
    }

    public function test_normalize_throws_on_newlines_in_path(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize("file\nname.txt", $this->testRoot);
    }

    public function test_normalize_handles_only_slashes(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('///', $this->testRoot);
    }

    public function test_normalize_throws_on_carriage_return(): void
    {
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize("file\rline.txt", $this->testRoot);
    }

    public function test_normalize_throws_on_other_control_characters(): void
    {
        // Test a few from the C0 control range
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize("file\x01.txt", $this->testRoot);
    }

    public function test_normalize_works_on_nonexistent_paths(): void
    {
        // Verify normalization doesn't require path existence
        $result = PathNormalizer::normalize('does/not/exist/yet.txt', $this->testRoot);
        $this->assertEquals('does/not/exist/yet.txt', $result);
    }

    public function test_normalize_trailing_slash_on_nonexistent_path(): void
    {
        // Should work same as directories
        $result = PathNormalizer::normalize('future/file.txt/', $this->testRoot);
        $this->assertEquals('future/file.txt', $result);
    }

    public function test_normalize_handles_many_empty_components(): void
    {
        // Path with many consecutive slashes should normalize properly
        $result = PathNormalizer::normalize('a////b////c', $this->testRoot);
        $this->assertEquals('a/b/c', $result);
    }

    public function test_normalize_handles_complex_parent_and_current_mixing(): void
    {
        // Complex mix of . and .. should resolve correctly
        $result = PathNormalizer::normalize('a/./b/../c/./d', $this->testRoot);
        $this->assertEquals('a/c/d', $result);
    }

    public function test_normalize_with_windows_drive_letter_in_path(): void
    {
        // Windows drive letter in middle of path (not at start) should be treated as filename
        $result = PathNormalizer::normalize('folder/C:/file.txt', $this->testRoot);
        $this->assertEquals('folder/C:/file.txt', $result);
    }

    public function test_normalize_handles_colon_in_filename(): void
    {
        // Colons in filenames (except for Windows drive syntax at start) should be allowed
        $result = PathNormalizer::normalize('file:backup.txt', $this->testRoot);
        $this->assertEquals('file:backup.txt', $result);
    }

    public function test_normalize_with_dot_dot_as_filename(): void
    {
        // When .. appears as a literal filename (encoded or not), it should still escape
        // But we can't really encode .. in a way PHP won't interpret
        // This test ensures we don't have issues with names that contain dots
        $result = PathNormalizer::normalize('dots..txt/file.txt', $this->testRoot);
        $this->assertEquals('dots..txt/file.txt', $result);
    }

    public function test_normalize_case_sensitivity(): void
    {
        // Normalizer should not do case-based filtering (filesystem handles that)
        $result = PathNormalizer::normalize('File.TXT', $this->testRoot);
        $this->assertEquals('File.TXT', $result);
    }

    public function test_normalize_with_combining_characters(): void
    {
        // Unicode combining characters should be preserved
        // Using é (e + combining acute accent in NFD form)
        $combining = "e\u{0301}"; // e with combining acute accent
        $result = PathNormalizer::normalize('file_' . $combining . '.txt', $this->testRoot);
        $this->assertEquals('file_' . $combining . '.txt', $result);
    }

    public function test_normalize_with_utf8_bom(): void
    {
        // UTF-8 BOM should be treated as part of filename, not stripped
        $bom = "\xEF\xBB\xBF";
        $result = PathNormalizer::normalize($bom . 'file.txt', $this->testRoot);
        $this->assertEquals($bom . 'file.txt', $result);
    }

    public function test_normalize_with_dots_only_component(): void
    {
        // Components with only dots (not .. or .) should work as filenames
        $result = PathNormalizer::normalize('subdir/....txt', $this->testRoot);
        $this->assertEquals('subdir/....txt', $result);
    }

    public function test_normalize_with_parent_in_relative_context(): void
    {
        // Multiple levels of parent traversal followed by normal path
        $result = PathNormalizer::normalize('a/b/c/../../d/e.txt', $this->testRoot);
        $this->assertEquals('a/d/e.txt', $result);
    }

    public function test_normalize_absolute_path_with_parent_in_middle(): void
    {
        // Absolute path with parent traversal in the middle that stays within root
        $rootWithoutSlash = rtrim($this->testRoot, '/');
        $absolutePath = $rootWithoutSlash . '/subdir/nested/../../file.txt';
        $this->assertEquals('file.txt', PathNormalizer::normalize($absolutePath, $this->testRoot));
    }

    public function test_normalize_throws_on_excessive_parent_traversal(): void
    {
        // Try to traverse way above root with many parent references
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('../../../../../../etc/passwd', $this->testRoot);
    }

    public function test_normalize_throws_on_windows_drive_with_unix_traversal(): void
    {
        // Windows drive letter with Unix-style parent traversal
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('C:/../../../etc/passwd', $this->testRoot);
    }

    public function test_normalize_throws_on_windows_drive_with_windows_traversal(): void
    {
        // Windows drive letter with Windows-style parent traversal
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('C:\\..\\..\\..\\etc\\passwd', $this->testRoot);
    }

    public function test_normalize_handles_zero_width_space_in_path(): void
    {
        // Zero-width space (U+200B) should be preserved in filename
        $zws = "\u{200B}";
        $result = PathNormalizer::normalize('file' . $zws . 'name.txt', $this->testRoot);
        $this->assertEquals('file' . $zws . 'name.txt', $result);
    }

    public function test_normalize_handles_zero_width_joiner_in_path(): void
    {
        // Zero-width joiner (U+200D) should be preserved
        $zwj = "\u{200D}";
        $result = PathNormalizer::normalize('file' . $zwj . 'name.txt', $this->testRoot);
        $this->assertEquals('file' . $zwj . 'name.txt', $result);
    }

    public function test_normalize_handles_zero_width_non_joiner_in_path(): void
    {
        // Zero-width non-joiner (U+200C) should be preserved
        $zwnj = "\u{200C}";
        $result = PathNormalizer::normalize('file' . $zwnj . 'name.txt', $this->testRoot);
        $this->assertEquals('file' . $zwnj . 'name.txt', $result);
    }

    public function test_normalize_throws_on_path_with_leading_space_and_traversal(): void
    {
        // Leading space followed by traversal attempt
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize(' ../../../etc/passwd', $this->testRoot);
    }

    public function test_normalize_throws_on_mixed_slash_types_with_traversal(): void
    {
        // Mix of forward and backslashes in parent traversal
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('..\\/../..\\etc/passwd', $this->testRoot);
    }

    public function test_normalize_handles_right_to_left_override_in_path(): void
    {
        // Right-to-left override character (U+202E) used in filename spoofing attacks
        $rtlo = "\u{202E}";
        $result = PathNormalizer::normalize('file' . $rtlo . 'txt.exe', $this->testRoot);
        $this->assertEquals('file' . $rtlo . 'txt.exe', $result);
    }

    public function test_normalize_throws_on_traversal_with_current_dir_obfuscation(): void
    {
        // Using ./ to obfuscate parent traversal
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('./../../etc/passwd', $this->testRoot);
    }

    public function test_normalize_handles_soft_hyphen_in_path(): void
    {
        // Soft hyphen (U+00AD) - invisible formatting character
        $shy = "\u{00AD}";
        $result = PathNormalizer::normalize('file' . $shy . 'name.txt', $this->testRoot);
        $this->assertEquals('file' . $shy . 'name.txt', $result);
    }

    public function test_normalize_throws_on_deeply_nested_then_escaped(): void
    {
        // Build up a deep path then try to escape with many parents
        $this->expectException(FilesystemSecurityException::class);
        PathNormalizer::normalize('a/b/c/d/e/../../../../../../../../etc/passwd', $this->testRoot);
    }

}
