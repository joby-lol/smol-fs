<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

/**
 * Helper methods for working with the filesystem. Not intended for public use.
 * 
 * @internal
 */
class FilesystemHelper
{

    /**
     * Recursively create directories as needed.
     * 
     * @throws FilesystemException if directory creation fails
     */
    public static function recursivelyCreateDirectory(string $path): void
    {
        if ($path === '' || is_dir($path))
            return;
        self::recursivelyCreateDirectory(dirname($path));
        if (is_file($path))
            throw new FilesystemException("Cannot create directory, a file exists at: {$path}");
        if (!mkdir($path))
            throw new FilesystemException("Failed to create directory: {$path}");
    }

    /**
     * Recursively delete a directory and all its contents.
     * 
     * @throws FilesystemException if deletion fails
     */
    public static function recursivelyDeleteDirectory(string $path): void
    {
        if (is_file($path))
            throw new FilesystemException("Cannot delete directory, a file exists at: {$path}");
        if (!is_dir($path))
            return;
        $items = scandir($path);
        if ($items === false)
            throw new FilesystemException("Failed to read directory contents: {$path}");
        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;
            $item_path = $path . '/' . $item;
            if (is_dir($item_path)) {
                self::recursivelyDeleteDirectory($item_path);
            }
            else {
                if (!unlink($item_path))
                    throw new FilesystemException("Failed to delete file: {$item_path}");
            }
        }
        if (!rmdir($path))
            throw new FilesystemException("Failed to delete directory: {$path}");
    }

    /**
     * Get a File representation for the given path, or null if it does not exist and $create is false. For creating a file, you should still use this method with $create set to true and then call write() from the returned File object.
     * 
     * Note that this method does not immediately create the file on disk or its parent directories; it only returns a File object that can be used to create or manipulate the file.
     * 
     * @throws FilesystemException if a directory exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside the given root
     */
    public static function getFileInDirectory(
        string $root,
        string $path,
        bool $create = false,
        string|null $relative_to = null,
    ): File|null
    {
        $path = PathNormalizer::normalize($path, rtrim($root, '/'), $relative_to);
        if (is_dir($root . $path))
            throw new FilesystemException("A directory already exists at the requested file path: {$root}{$path}");
        if (!$create && !is_file($root . $path))
            return null;
        return new File($root . $path, $root);
    }

    /**
     * Get a Directory representation for the given path, or null if it does not exist and $create is false. For creating a directory, you should still use this method with $create set to true and then call write() from the returned Directory object.
     * 
     * Note that this method does not immediately create the directory on disk or its parent directories; it only returns a Directory object that can be used to create or manipulate the directory.
     * 
     * @throws FilesystemException if a file exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside the given root
     */
    public static function getDirectoryInDirectory(
        string $root,
        string $path,
        bool $create = false,
        string|null $relative_to = null,
    ): Directory|null
    {
        $path = PathNormalizer::normalize($path, rtrim($root, '/'), $relative_to);
        if (is_file($root . $path))
            throw new FilesystemException("A file already exists at the requested directory path: {$root}{$path}");
        if (!$create && !is_dir($root . $path))
            return null;
        return new Directory($root . $path, $root);
    }

    /**
     * Get an array of File objects representing the files in the specified base path directory. If $glob is provided, only files matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
     * 
     * Glob brace is enabled, so you can use the following special characters:
     * - *: matches any number of any characters except directory separators
     * - ?: matches any single character except directory separators
     * - [...]: matches any one of the enclosed characters, if is ! matches any character not enclosed
     * - {x,y,z}: matches any of the comma-separated subpatterns x, y, z
     * - \: escapes the next character
     * 
     * @param string|null $glob optional glob pattern to match files against
     * @param (callable(File):bool)|null $filter optional filter function that takes a File object and returns true to include it, false to exclude it
     * @return File[] array of File objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside the given root
     */
    public static function getFilesInDirectory(
        string $root,
        string $base_path,
        string|null $glob,
        callable|null $filter,
        string|null $relative_to = null,
    ): array
    {
        $glob = $glob ?? '*';
        $full_glob = ($relative_to ?? $base_path) . '/' . $glob;
        $items = glob($full_glob, GLOB_BRACE);
        if ($items === false)
            throw new FilesystemException("Failed to read directory contents with glob: {$full_glob}");
        $files = [];
        foreach ($items as $item) {
            if (is_dir($item))
                continue;
            $path = PathNormalizer::normalize($item, $root, $relative_to);
            $files[] = new File($root . $path, $root);
        }
        if ($filter)
            $files = array_filter($files, $filter);
        return array_values($files);
    }

    /**
     * Get an array of Directory objects representing the directories in the given base path directory. If $glob is provided, only directories matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
     * 
     * Glob brace is enabled, so you can use the following special characters:
     * - *: matches any number of any characters except directory separators
     * - ?: matches any single character except directory separators
     * - [...]: matches any one of the enclosed characters, if is ! matches any character not enclosed
     * - {x,y,z}: matches any of the comma-separated subpatterns x, y, z
     * - \: escapes the next character
     * 
     * @param string|null $glob optional glob pattern to match directories against
     * @param (callable(Directory):bool)|null $filter optional filter function that takes a Directory object and returns true to include it, false to exclude it
     * @return Directory[] array of Directory objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public static function getDirectoriesInDirectory(
        string $root,
        string $base_path,
        string|null $glob,
        callable|null $filter,
        string|null $relative_to = null,
    ): array
    {
        $glob = $glob ?? '*';
        $full_glob = ($relative_to ?? $base_path) . '/' . $glob;
        $items = glob($full_glob, GLOB_BRACE | GLOB_ONLYDIR);
        if ($items === false)
            throw new FilesystemException("Failed to read directory contents with glob: {$full_glob}");
        $directories = [];
        foreach ($items as $item) {
            $path = PathNormalizer::normalize($item, $root, $relative_to);
            $directories[] = new Directory($root . $path, $root);
        }
        if ($filter)
            $directories = array_filter($directories, $filter);
        return array_values($directories);
    }

}
