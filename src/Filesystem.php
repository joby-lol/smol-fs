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
class Filesystem implements FilesystemInterface
{

    /**
     * The root directory of this Filesystem, with a trailing slash.
     */
    public readonly string $root;

    public function __construct(
        string $root,
    )
    {
        $root = realpath($root);
        if ($root === false)
            throw new FilesystemException("this Filesystem's root directory does not exist: {$root}");
        if (!is_dir($root))
            throw new FilesystemException("this Filesystem's root path is not a directory: {$root}");
        // normalize to forward slashes, even on Windows
        $this->root = str_replace('\\', '/', $root) . '/';
    }

    /**
     * @inheritDoc
     */
    public function copy(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void
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
     * @inheritDoc
     */
    public function move(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void
    {
        $this->copy($source, $destination, $allow_overwrite);
        $source = $this->root . PathNormalizer::normalize((string) $source, $this->root);
        unlink($source);
    }

    /**
     * @inheritDoc
     */
    public function copyOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void
    {
        $source = $this->root . PathNormalizer::normalize((string) $source, $this->root);
        if (!is_file($source))
            throw new FilesystemException("Source file does not exist: {$source}");
        if (is_file($destination) && !$allow_overwrite)
            throw new FilesystemException("Destination file already exists and overwriting is not allowed: {$destination}");
        FilesystemHelper::recursivelyCreateDirectory(dirname($destination));
        copy($source, $destination);
    }

    /**
     * @inheritDoc
     */
    public function moveOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void
    {
        $this->copyOut($source, $destination, $allow_overwrite);
        $source = $this->root . PathNormalizer::normalize((string) $source, $this->root);
        unlink($source);
    }

    /**
     * @inheritDoc
     */
    public function copyIn(string $source, string|FileInterface $destination, bool $allow_overwrite): void
    {
        if (is_uploaded_file($source))
            throw new FilesystemException("Cannot copy uploaded file - use moveIn() instead: {$source}");

        $destination = $this->root . PathNormalizer::normalize((string) $destination, $this->root);
        if (!is_file($source))
            throw new FilesystemException("Source file does not exist: {$source}");
        if (is_file($destination) && !$allow_overwrite)
            throw new FilesystemException("Destination file already exists and overwriting is not allowed: {$destination}");
        FilesystemHelper::recursivelyCreateDirectory(dirname($destination));
        copy($source, $destination);
    }

    /**
     * @inheritDoc
     */
    public function moveIn(string $source, string|FileInterface $destination, bool $allow_overwrite, bool $allow_uploaded_files = false): void
    {
        $destination = $this->root . PathNormalizer::normalize((string) $destination, $this->root);
        if (!is_file($source))
            throw new FilesystemException("Source file does not exist: {$source}");
        if (is_file($destination) && !$allow_overwrite)
            throw new FilesystemException("Destination file already exists and overwriting is not allowed: {$destination}");
        FilesystemHelper::recursivelyCreateDirectory(dirname($destination));

        if (is_uploaded_file($source)) {
            if (!$allow_uploaded_files)
                throw new FilesystemException("Moving in uploaded file blocked: {$source}");
            move_uploaded_file($source, $destination);
        }
        else {
            copy($source, $destination);
            unlink($source);
        }
    }

    /**
     * @inheritDoc
     * 
     * @return ($create is true ? File : File|null)
     */
    public function file(string $path, bool $create = false): File|null
    {
        return FilesystemHelper::getFileInDirectory($this->root, $path, $create);
    }

    /**
     * @inheritDoc
     * 
     * @param string $glob optional glob pattern to match files against
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a File object and returns true to include it, false to exclude it
     * @return File|null
     */
    public function globFile(string $glob, callable|null $filter = null): File|null
    {
        $files = FilesystemHelper::getFilesInDirectory($this->root, rtrim($this->root, '/'), $glob, $filter);
        return count($files) > 0 ? $files[0] : null;
    }

    /**
     * @inheritDoc
     * 
     * @return ($create is true ? Directory : Directory|null)
     */
    public function directory(string $path, bool $create = false): Directory|null
    {
        return FilesystemHelper::getDirectoryInDirectory($this->root, $path, $create);
    }

    /**
     * @inheritDoc
     * 
     * @param string $glob optional glob pattern to match directories against
     * @param (callable(DirectoryInterface):bool)|null $filter optional filter function that takes a DirectoryInterface object and returns true to include it, false to exclude it
     * @return Directory|null
     */
    public function globDirectory(string $glob, callable|null $filter = null): Directory|null
    {
        $dirs = FilesystemHelper::getDirectoriesInDirectory($this->root, rtrim($this->root, '/'), $glob, $filter);
        return count($dirs) > 0 ? $dirs[0] : null;
    }

    /**
     * @inheritDoc
     * 
     * @param string|null $glob optional glob pattern to match files against
     * @param (callable(FileInterface):bool)|null $filter optional filter function that takes a File object and returns true to include it, false to exclude it
     * @return File[] array of File objects
     */
    public function files(string|null $glob = null, callable|null $filter = null): array
    {
        return FilesystemHelper::getFilesInDirectory($this->root, rtrim($this->root, '/'), $glob, $filter);
    }

    /**
     * @inheritDoc
     * 
     * @param string|null $glob optional glob pattern to match directories against
     * @param (callable(DirectoryInterface):bool)|null $filter optional filter function that takes a DirectoryInterface object and returns true to include it, false to exclude it
     * @return Directory[] array of Directory objects
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

    /**
     * @inheritDoc
     */
    public function contains(string $path): bool
    {
        $path = PathNormalizer::normalize($path, null);
        return str_starts_with($path, $this->root);
    }

}
