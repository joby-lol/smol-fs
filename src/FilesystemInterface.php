<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

/**
 * Representation of the filesystem root, with utility methods for working with it. Will throw a FilesystemSecurityException upon detecting a traversal above this Filesystem's root directory.
 */
interface FilesystemInterface
{

    /**
     * Copy a file from source to destination. If $allow_overwrite is false and the destination file exists, an exception will be thrown. Accepts File objects and both relative and absolute paths, but will throw an exception if any of the provided paths are outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if any paths resolve to outside this Filesystem's root
     */
    public function copy(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void;

    /**
     * Move a file from source to destination. If $allow_overwrite is false and the destination file exists, an exception will be thrown. Accepts File objects and both relative and absolute paths, but will throw an exception if any of the provided paths are outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if any paths resolve to outside this Filesystem's root
     */
    public function move(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void;

    /**
     * Copy a file from a source inside this Filesystem to a destination anywhere else. Accepts File objects and both relative and absolute paths, but will throw an exception if the source path is outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if the source path resolves to outside this Filesystem's root
     */
    public function copyOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void;

    /**
     * Move a file from a source inside this Filesystem to a destination anywhere else. Accepts File objects and both relative and absolute paths, but will throw an exception if the source path is outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if the source path resolves to outside this Filesystem's root
     */
    public function moveOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void;

    /**
     * Copy a file from a source anywhere else to a destination inside this Filesystem. Accepts File objects and both relative and absolute paths, but will throw an exception if the destination path is outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if the destination path resolves to outside this Filesystem's root
     */
    public function copyIn(string $source, string|FileInterface $destination, bool $allow_overwrite): void;

    /**
     * Move a file from a source anywhere else to a destination inside this Filesystem. Accepts File objects and both relative and absolute paths, but will throw an exception if the destination path is outside this Filesystem's root.
     * 
     * @throws FilesystemException if a directory exists at the given target location
     * @throws FilesystemSecurityException if the destination path resolves to outside this Filesystem's root
     */
    public function moveIn(string $source, string|FileInterface $destination, bool $allow_overwrite, bool $allow_uploaded_files = false): void;

    /**
     * Get a File representation for the given path, or null if it does not exist and $create is false. For creating a file, you should still use this method with $create set to true and then call write() from the returned File object.
     * 
     * Note that this method does not immediately create the file on disk or its parent directories; it only returns a File object that can be used to create or manipulate the file.
     * 
     * @return ($create is true ? FileInterface : FileInterface|null)
     * 
     * @throws FilesystemException if a directory exists at the given file path
     * @throws FilesystemSecurityException if the path resolves to outside the given directory
     */
    public function file(string $path, bool $create = false): FileInterface|null;

    /**
     * Get the first File object matching the given glob pattern in this filesystem, or null if none match. If $filter is provided, it will be called for each File object and only those for which it returns true will be considered.
     * 
     * Glob brace is enabled, so you can use the following special characters:
     * - *: matches any number of any characters except directory separators
     * - ?: matches any single character except directory separators
     * - [...]: matches any one of the enclosed characters, if is ! matches any character not enclosed
     * - {x,y,z}: matches any of the comma-separated subpatterns x, y, z
     * - \: escapes the next character
     * 
     * @param string $glob optional glob pattern to match files against
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a File object and returns true to include it, false to exclude it
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
     * @throws FilesystemException if a file exists at this Filesystem's root path
     * @throws FilesystemSecurityException if the path resolves to outside this Filesystem's root
     */
    public function directory(string $path, bool $create = false): DirectoryInterface|null;

    /**
     * Get the first Directory object matching the given glob pattern in this filesystem, or null if none match. If $filter is provided, it will be called for each Directory object and only those for which it returns true will be considered.
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
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a File object and returns true to include it, false to exclude it
     * @return FileInterface[] array of File objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public function files(string|null $glob = null, callable|null $filter = null): array;

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
     * @param (callable(DirectoryInterface):bool)|null $filter optional filter function that takes a DirectoryInterface object and returns true to include it, false to exclude it
     * @return DirectoryInterface[] array of DirectoryInterface objects
     * 
     * @throws FilesystemException if reading the directory contents fails
     * @throws FilesystemSecurityException if any paths resolve to outside this directory
     */
    public function directories(string|null $glob = null, callable|null $filter = null): array;

    /**
     * Determine whether a given absolute path is contained within this Filesystem.
     */
    public function contains(string $path): bool;

}
