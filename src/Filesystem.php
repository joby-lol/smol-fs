<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

/**
 * Representation of the filesystem root, with utility methods for working with it. Will throw a FilesystemSecurityException upon detecting a traversal above the given root directory.
 */
class Filesystem
{

    public readonly string $root;

    public function __construct(
        string $root,
    )
    {
        $root = realpath($root);
        if ($root === false)
            throw new FilesystemException("The given root directory does not exist: {$root}");
        if (!is_dir($root))
            throw new FilesystemException("The given root path is not a directory: {$root}");
        // normalize to forward slashes, even on Windows
        $this->root = str_replace('\\', '/', $root) . '/';
    }

    /**
     * Copy a file from source to destination. If $allow_overwrite is false and the destination file exists, an exception will be thrown. Accepts File objects and both relative and absolute paths, but will throw an exception if any of the provided paths are outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if any paths resolve to outside the given root
     */
    public function copy(string|File $source, string|File $destination, bool $allow_overwrite): void
    {
        $source = $this->root . PathNormalizer::normalize((string) $source, $this->root);
        $destination = $this->root . PathNormalizer::normalize((string) $destination, $this->root);
        if (!is_file($source))
            throw new FilesystemException("Source file does not exist: {$source}");
        if (is_file($destination) && !$allow_overwrite)
            throw new FilesystemException("Destination file already exists and overwriting is not allowed: {$destination}");
        FilesystemHelper::recursivelyCreateDirectory(dirname($destination));
        copy($source, $destination);
    }

    /**
     * Move a file from source to destination. If $allow_overwrite is false and the destination file exists, an exception will be thrown. Accepts File objects and both relative and absolute paths, but will throw an exception if any of the provided paths are outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if any paths resolve to outside the given root
     */
    public function move(string|File $source, string|File $destination, bool $allow_overwrite): void
    {
        $this->copy($source, $destination, $allow_overwrite);
        $source = $this->root . PathNormalizer::normalize((string) $source, $this->root);
        unlink($source);
    }

    /**
     * Get a File representation for the given path, or null if it does not exist and $create is false. For creating a file, you should still use this method with $create set to true and then call write() from the returned File object.
     * 
     * Note that this method does not immediately create the file on disk or its parent directories; it only returns a File object that can be used to create or manipulate the file.
     * 
     * @throws FilesystemException if a directory exists at the given file path
     * @throws FilesystemSecurityException if the path resolves to outside the given directory
     */
    public function file(string $path, bool $create = false): File|null
    {
        return FilesystemHelper::getFileInDirectory($this->root, $path, $create);
    }

    /**
     * Get a Directory representation for the given path, or null if it does not exist and $create is false. For creating a directory, you should still use this method with $create set to true and then call write() from the returned Directory object.
     * 
     * Note that this method does not immediately create the directory on disk or its parent directories; it only returns a Directory object that can be used to create or manipulate the directory.
     * 
     * @throws FilesystemException if a file exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside the given root
     */
    public function directory(string $path, bool $create = false): Directory|null
    {
        return FilesystemHelper::getDirectoryInDirectory($this->root, $path, $create);
    }

    /**
     * Get an array of File objects representing the files in the root directory. If $glob is provided, only files matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
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
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public function files(string|null $glob = null, callable|null $filter = null): array
    {
        return FilesystemHelper::getFilesInDirectory($this->root, rtrim($this->root, '/'), $glob, $filter);
    }

    /**
     * Get an array of Directory objects representing the directories in the root directory. If $glob is provided, only directories matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
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
    public function directories(string|null $glob = null, callable|null $filter = null): array
    {
        return FilesystemHelper::getDirectoriesInDirectory($this->root, rtrim($this->root, '/'), $glob, $filter);
    }

    /**
     * Get a new Filesystem instance rooted at the given path relative to this Filesystem's root. The directory will be immediately created if it does not already exist.
     * 
     * @throws FilesystemSecurityException if the path resolves to outside this Filesystem's root
     * @throws FilesystemException if creating the directory fails
     */
    public function filesystem(string $path): Filesystem
    {
        $new_root = $this->root . PathNormalizer::normalize($path, $this->root);
        FilesystemHelper::recursivelyCreateDirectory($new_root);
        return new Filesystem($new_root);
    }

}
