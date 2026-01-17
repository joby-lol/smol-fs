<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use DateTime;
use Stringable;

/**
 * Representation of a single directory, with utility methods for working with it.
 */
class Directory implements Stringable
{

    /**
     * @param string $path The full path to the directory, *without* a trailing slash
     * @internal create from Filesystem instead of instantiating directly
     */
    public function __construct(
        public readonly string $path,
        public readonly string $root,
    ) {}

    /**
     * Create the directory and any parent directories as needed, if it does not already exist.
     */
    public function create(): static
    {
        FilesystemHelper::recursivelyCreateDirectory($this->path);
        return $this;
    }

    /**
     * Delete the directory. If $recursive is true, delete all contents recursively.
     * 
     * @throws FilesystemException if deletion fails
     */
    public function delete(bool $recursive = false): static
    {
        // Delete the directory. If $recursive is true, delete all contents recursively.
        if ($recursive) {
            FilesystemHelper::recursivelyDeleteDirectory($this->path);
            return $this;
        }
        // Non-recursive delete and return
        if (!rmdir($this->path))
            throw new FilesystemException("Failed to delete directory: {$this->path}");
        return $this;
    }

    /**
     * Get a File representation for the given path, or null if it does not exist and $create is false. For creating a file, you should still use this method with $create set to true and then call write() from the returned File object.
     * 
     * Note that this method does not immediately create the file on disk or its parent directories; it only returns a File object that can be used to create or manipulate the file.
     * 
     * @throws FilesystemException if a directory exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside this directory
     */
    public function file(string $path, bool $create = false): File|null
    {
        return FilesystemHelper::getFileInDirectory($this->root, $path, $create, $this->path);
    }

    /**
     * Get a Directory representation for the given path, or null if it does not exist and $create is false. For creating a directory, you should still use this method with $create set to true and then call write() from the returned Directory object.
     * 
     * Note that this method does not immediately create the directory on disk or its parent directories; it only returns a Directory object that can be used to create or manipulate the directory.
     * 
     * @throws FilesystemException if a file exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside this directory
     */
    public function directory(string $path, bool $create = false): Directory|null
    {
        return FilesystemHelper::getDirectoryInDirectory($this->root, $path, $create, $this->path);
    }

    /**
     * Get an array of File objects representing the files in this directory. If $glob is provided, only files matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
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
        return FilesystemHelper::getFilesInDirectory($this->root, $this->path, $glob, $filter);
    }

    /**
     * Get an array of Directory objects representing the directories in this directory. If $glob is provided, only directories matching the glob pattern will be returned. If $filter is provided, it will be called for each File object and only those for which it returns true will be included.
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
        return FilesystemHelper::getDirectoriesInDirectory($this->root, $this->path, $glob, $filter);
    }

    /**
     * Get the last modified time of the directory, or null if it does not exist.
     * 
     * @throws FilesystemException if getting the modification time fails
     */
    public function modified(): DateTime|null
    {
        if (!$this->exists())
            return null;
        $timestamp = filemtime($this->path);
        if ($timestamp === false)
            throw new FilesystemException("Failed to get modification time for directory: {$this->path}");
        return (new DateTime())->setTimestamp($timestamp);
    }

    /**
     * Check if the directory exists.
     */
    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Get the base name of the directory (the last part of the path).
     */
    public function basename(): string
    {
        return basename($this->path);
    }

    /**
     * Get the path of the directory relative to the root directory.
     */
    public function relativePath(): string
    {
        return substr($this->path, strlen($this->root));
    }

    public function __toString(): string
    {
        return $this->path;
    }

}
