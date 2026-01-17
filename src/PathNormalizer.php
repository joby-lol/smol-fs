<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

/**
 * Helper class for normalizing and validating paths.
 * 
 * @internal
 */
class PathNormalizer
{

    /**
     * Normalize and validate either a relative or absolute path and return it as a relative path relative to this Filesystem's root. Throws a FilesystemSecurityException if path traversal above the root directory or anything else suspicious is detected.
     * 
     * If $relative_to is provided, relative paths will be normalized relative to it, otherwise they will be normalized relative to the root.
     * 
     * @throws FilesystemSecurityException if anything suspicious is detected in the path
     */
    public static function normalize(string $path, string $root, string|null $relative_to = null): string
    {
        // Validate for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $path))
            throw new FilesystemSecurityException("Path contains invalid control characters");
        // Normalize slashes
        $path_split = self::normalizeSlashes($path);
        $root = self::normalizeSlashes($root);
        $relative_to = $relative_to === null
            ? $root
            : self::normalizeSlashes($relative_to);
        // if this is a relative path, prepend the relative_to base path or root
        $absolute = str_starts_with($path_split, '/') || preg_match('/^[A-Za-z]:\//', $path_split);
        if (!$absolute) {
            if (!str_ends_with($relative_to, '/'))
                $relative_to .= '/';
            $path_split = $relative_to . $path_split;
        }
        // split into an array for further normalizing
        if ($path_split != '/')
            $path_split = rtrim($path_split, '/');
        $path_split = explode('/', $path_split);
        return self::normalizeAbsolutePath($path_split, $root);
    }

    protected static function normalizeSlashes(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if ($normalized === '')
            return '';
        $normalized = preg_replace('/\/+/', '/', $normalized);
        if ($normalized === null)
            throw new FilesystemSecurityException("Failed to normalize slashes: {$path}");
        return $normalized;
    }

    /**
     * @param string[] $path
     * @throws FilesystemSecurityException
     */
    protected static function normalizeAbsolutePath(array $path, string $root): string
    {
        $normalized = [];
        if (!str_ends_with($root, '/'))
            $root .= '/';
        foreach ($path as $part) {
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($normalized))
                    throw new FilesystemSecurityException("Path traversal above filesystem root detected");
                array_pop($normalized);
                continue;
            }
            $normalized[] = $part;
        }
        $normalized = implode('/', $normalized);
        if (!str_starts_with($normalized . '/', $root))
            throw new FilesystemSecurityException("Path traversal above allowed root detected");
        $normalized = substr($normalized, strlen($root));
        return $normalized;
    }

}
