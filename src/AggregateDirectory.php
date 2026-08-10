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
 * Representation of one or more directories that all exist at the same relative path to multiple Filesystems. Operations will occur on the first-matched item. Operations that return child files/directories will themselves aggregate from all sources.
 */
class AggregateDirectory implements DirectoryInterface
{

    /**
     * Internal array of directories to query, in descending precedence order. Writes/creations will occur in the first one by default.
     * @var array<DirectoryInterface>
     */
    protected array $directories = [];

    public function __construct(
        protected AggregateFilesystem $root,
        DirectoryInterface $main_directory,
        DirectoryInterface ...$directories,
    )
    {
        $this->directories = [$main_directory, ...$directories];
    }

    /**
     * @inheritDoc
     */
    public function basename(): string
    {
        return $this->directories[0]->basename();
    }

    /**
     * @inheritDoc
     */
    public function contains(string $path): bool
    {
        foreach ($this->directories as $dir)
            if ($dir->contains($path))
                return true;
        return false;
    }

    /**
     * @inheritDoc
     */
    public function create(): static
    {
        $this->directories[0]->create();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(bool $recursive = false): static
    {
        foreach ($this->directories as $dir)
            $dir->delete($recursive);
        return $this;
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateDirectory[]
     */
    public function directories(string|null $glob = null, callable|null $filter = null): array
    {
        /** @var array<string,DirectoryInterface[]> $directories */
        $directories = [];
        foreach ($this->directories as $dir)
            foreach ($dir->directories($glob, $filter) as $dir)
                $directories[$dir->relativePath()][] = $dir;
        return array_values(array_map(
            fn(array $dirs): AggregateDirectory => new AggregateDirectory($this->root, ...$dirs),
            $directories,
        ));
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateDirectory
     */
    public function directory(string $path, bool $create = false): AggregateDirectory|null
    {
        $directories = array_filter(array_map(
            fn(DirectoryInterface $dir): DirectoryInterface|null => $dir->directory($path, $create),
            $this->directories,
        ));
        if (!$directories)
            return null;
        return new AggregateDirectory($this->root, ...$directories);
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return $this->directories[0]->exists();
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateFile|null
     */
    public function file(string $path, bool $create = false): AggregateFile|null
    {
        $files = array_filter(array_map(
            fn(DirectoryInterface $dir): FileInterface|null => $dir->file($path, $create),
            $this->directories,
        ));
        if (!$files)
            return null;
        return new AggregateFile($this->root, ...$files);
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateFile[]
     */
    public function files(string|null $glob = null, callable|null $filter = null): array
    {
        /** @var array<string,FileInterface[]> $files */
        $files = [];
        foreach ($this->directories as $dir)
            foreach ($dir->files($glob, $filter) as $file)
                $files[$file->relativePath()][] = $file;
        return array_values(array_map(
            fn(array $files): AggregateFile => new AggregateFile($this->root, ...$files),
            $files,
        ));
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateDirectory|null
     */
    public function globDirectory(string $glob, callable|null $filter = null): AggregateDirectory|null
    {
        $directories = $this->directories($glob, $filter);
        return $directories ? $directories[0] : null;
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateFile|null
     */
    public function globFile(string $glob, callable|null $filter = null): AggregateFile|null
    {
        $files = $this->files($glob, $filter);
        return $files ? $files[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function modified(): DateTimeImmutable|null
    {
        return $this->directories[0]->modified();
    }

    /**
     * @inheritDoc
     */
    public function relativePath(): string
    {
        return $this->directories[0]->relativePath();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->directories[0]->__toString();
    }

}
