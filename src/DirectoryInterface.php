<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use DateTimeImmutable;
use Stringable;

/**
 * Representation of a single directory, with utility methods for working with it.
 */
interface DirectoryInterface extends Stringable
{

    /**
     * Create the directory and any parent directories as needed, if it does not already exist.
     */
    public function create(): static;

    /**
     * Delete the directory. If $recursive is true, delete all contents recursively.
     * 
     * @throws FilesystemException if deletion fails
     */
    public function delete(bool $recursive = false): static;

    /**
     * Get a File representation for the given path, or null if it does not exist and $create is false. For creating a file, you should still use this method with $create set to true and then call write() from the returned File object.
     * 
     * Note that this method does not immediately create the file on disk or its parent directories; it only returns a File object that can be used to create or manipulate the file.
     * 
     * @return ($create is true ? FileInterface : FileInterface|null)
     * 
     * @throws FilesystemException if a directory exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside this directory
     */
    public function file(string $path, bool $create = false): FileInterface|null;

    /**
     * Get the first File object matching the given glob pattern in this directory, or null if none match. If $filter is provided, it will be called for each File object and only those for which it returns true will be considered.
     * 
     * Glob brace is enabled, so you can use the following special characters:
     * - *: matches any number of any characters except directory separators
     * - ?: matches any single character except directory separators
     * - [...]: matches any one of the enclosed characters, if is ! matches any character not enclosed
     * - {x,y,z}: matches any of the comma-separated subpatterns x, y, z
     * - \: escapes the next character
     * 
     * @param string $glob optional glob pattern to match files against
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a FileInterface object and returns true to include it, false to exclude it
     * @return FileInterface|null
     */
    public function globFile(string $glob, callable|null $filter = null): FileInterface|null;

    /**
     * Get a Directory representation for the given path, or null if it does not exist and $create is false. For creating a directory, you should still use this method with $create set to true and then call write() from the returned Directory object.
     * 
     * Note that this method does not immediately create the directory on disk or its parent directories; it only returns a Directory object that can be used to create or manipulate the directory.
     * 
     * @return ($create is true ? DirectoryInterface : DirectoryInterface|null)
     * 
     * @throws FilesystemException if a file exists at the given root path
     * @throws FilesystemSecurityException if the path resolves to outside this directory
     */
    public function directory(string $path, bool $create = false): DirectoryInterface|null;

    /**
     * Get the first Directory object matching the given glob pattern in this directory, or null if none match. If $filter is provided, it will be called for each Directory object and only those for which it returns true will be considered.
     * 
     * Glob brace is enabled, so you can use the following special characters:
     * - *: matches any number of any characters except directory separators
     * - ?: matches any single character except directory separators
     * - [...]: matches any one of the enclosed characters, if is ! matches any character not enclosed
     * - {x,y,z}: matches any of the comma-separated subpatterns x, y, z
     * - \: escapes the next character
     * 
     * @param string $glob optional glob pattern to match directories against
     * @param (callable(DirectoryInterface):bool)|null $filter optional filter function that takes a DirectoryInterface object and returns true to include it, false to exclude it
     * @return DirectoryInterface|null
     */
    public function globDirectory(string $glob, callable|null $filter = null): DirectoryInterface|null;

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
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a FileInterface object and returns true to include it, false to exclude it
     * @return FileInterface[] array of FileInterface objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public function files(string|null $glob = null, callable|null $filter = null): array;

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
     * @param (callable(DirectoryInterface):bool)|null $filter optional filter function that takes a DirectoryInterface object and returns true to include it, false to exclude it
     * @return DirectoryInterface[] array of Directory objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public function directories(string|null $glob = null, callable|null $filter = null): array;

    /**
     * Get the last modified time of the directory, or null if it does not exist.
     * 
     * @throws FilesystemException if getting the modification time fails
     */
    public function modified(): DateTimeImmutable|null;

    /**
     * Check if the directory exists.
     */
    public function exists(): bool;

    /**
     * Get the base name of the directory (the last part of the path).
     */
    public function basename(): string;

    /**
     * Get the path of the directory relative to the root directory.
     */
    public function relativePath(): string;

    /**
     * Determine whether a given absolute path is contained within this directory.
     */
    public function contains(string $path): bool;

}
