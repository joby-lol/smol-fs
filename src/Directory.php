<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use DateTimeImmutable;

/**
 * Representation of a single directory, with utility methods for working with it.
 */
class Directory implements DirectoryInterface
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
     * @inheritDoc
     */
    public function create(): static
    {
        FilesystemHelper::recursivelyCreateDirectory($this->path);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(bool $recursive = false): static
    {
        // Delete the directory. If $recursive is true, delete all contents recursively.
        if ($recursive) {
            FilesystemHelper::recursivelyDeleteDirectory($this->path);
            return $this;
        }
        // Non-recursive delete and return
        if (!@rmdir($this->path))
            throw new FilesystemException("Failed to delete directory: {$this->path}");
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function file(string $path, bool $create = false): File|null
    {
        return FilesystemHelper::getFileInDirectory($this->root, $path, $create, $this->path);
    }

    /**
     * @inheritDoc
     */
    public function globFile(string $glob, callable|null $filter = null): File|null
    {
        $files = FilesystemHelper::getFilesInDirectory($this->root, $this->path, $glob, $filter);
        return count($files) > 0 ? $files[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function directory(string $path, bool $create = false): Directory|null
    {
        return FilesystemHelper::getDirectoryInDirectory($this->root, $path, $create, $this->path);
    }

    /**
     * @inheritDoc
     */
    public function globDirectory(string $glob, callable|null $filter = null): Directory|null
    {
        $dirs = FilesystemHelper::getDirectoriesInDirectory($this->root, $this->path, $glob, $filter);
        return count($dirs) > 0 ? $dirs[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function files(string|null $glob = null, callable|null $filter = null): array
    {
        return FilesystemHelper::getFilesInDirectory($this->root, $this->path, $glob, $filter);
    }

    /**
     * @inheritDoc
     */
    public function directories(string|null $glob = null, callable|null $filter = null): array
    {
        return FilesystemHelper::getDirectoriesInDirectory($this->root, $this->path, $glob, $filter);
    }

    /**
     * @inheritDoc
     */
    public function modified(): DateTimeImmutable|null
    {
        if (!$this->exists())
            return null;
        $timestamp = filemtime($this->path);
        if ($timestamp === false)
            throw new FilesystemException("Failed to get modification time for directory: {$this->path}");
        // @phpstan-ignore-next-line
        return DateTimeImmutable::createFromFormat("U", (string) $timestamp);
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * @inheritDoc
     */
    public function basename(): string
    {
        return basename($this->path);
    }

    /**
     * @inheritDoc
     */
    public function relativePath(): string
    {
        return substr($this->path, strlen($this->root));
    }

    /**
     * @inheritDoc
     */
    public function contains(string $path): bool
    {
        $path = PathNormalizer::normalize($path, null);
        return str_starts_with($path, $this);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->path;
    }

}
