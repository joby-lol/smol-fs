<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use Generator;

/**
 * Helper for working with multiple filesystem root as if they were a single filesystem. Allows multiple Filesystem objects to be aggregated and queried.
 */
class AggregateFilesystem implements FilesystemInterface
{

    /**
     * Internal array of medium-priority filesystems to query, in descending precedence order. Writes/creations will occur in the first one in the highest priority by default.
     * @var array<FilesystemInterface>
     */
    protected array $filesystems_high = [];

    /**
     * Internal array of medium-priority filesystems to query, in descending precedence order. Writes/creations will occur in the first one in the highest priority by default.
     * @var array<FilesystemInterface>
     */
    protected array $filesystems_medium = [];

    /**
     * Internal array of low-priority filesystems to query, in descending precedence order. Writes/creations will occur in the first one in the highest priority by default.
     * @var array<FilesystemInterface>
     */
    protected array $filesystems_low = [];

    public function __construct(
        FilesystemInterface ...$filesystems,
    )
    {
        $this->filesystems_medium = $filesystems;
    }

    /**
     * Add a new medium-priority sub-filesystem. By default they are added to the end of the list, making them the lowest priority within this category. They can also be added to the front of the list by setting $add_to_front.
     */
    public function addFilesystem(FilesystemInterface $filesystem, bool $add_to_front = false): void
    {
        if ($add_to_front)
            array_unshift($this->filesystems_medium, $filesystem);
        else
            array_push($this->filesystems_medium, $filesystem);
    }

    /**
     * Add a new high-priority sub-filesystem. By default they are added to the end of the list, making them the lowest priority within this category. They can also be added to the front of the list by setting $add_to_front.
     */
    public function addHighPriorityFilesystem(FilesystemInterface $filesystem, bool $add_to_front = false): void
    {
        if ($add_to_front)
            array_unshift($this->filesystems_high, $filesystem);
        else
            array_push($this->filesystems_high, $filesystem);
    }

    /**
     * Add a new low-priority sub-filesystem. By default they are added to the end of the list, making them the lowest priority within this category. They can also be added to the front of the list by setting $add_to_front.
     */
    public function addLowPriorityFilesystem(FilesystemInterface $filesystem, bool $add_to_front = false): void
    {
        if ($add_to_front)
            array_unshift($this->filesystems_low, $filesystem);
        else
            array_push($this->filesystems_low, $filesystem);
    }

    /**
     * @inheritDoc
     * 
     * In an AggregateFilesystem the source and destination must be within one of the configured filesystems, and if it is a FileInterface object it will be placed in its own parent filesystem.
     */
    public function copy(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void
    {
        if (!$this->contains($source))
            throw new FilesystemException("Source file must be inside AggregateFilesystem: ${source}");
        if (!($destination_fs = $this->parentFilesystem($destination)))
            throw new FilesystemException("Destination file must be inside AggregateFilesystem: ${destination}");
        $destination_fs->copyIn($source, $destination, $allow_overwrite);
    }

    /**
     * @inheritDoc
     */
    public function copyIn(string $source, string|FileInterface $destination, bool $allow_overwrite): void
    {
        if (!($destination_fs = $this->parentFilesystem($destination)))
            throw new FilesystemException("Destination file must be inside AggregateFilesystem: ${destination}");
        $destination_fs->copyIn($source, $destination, $allow_overwrite);
    }

    /**
     * @inheritDoc
     */
    public function copyOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void
    {
        if (!($source_fs = $this->parentFilesystem($source)))
            throw new FilesystemException("Source file must be inside AggregateFilesystem: ${source}");
        $source_fs->copyOut($source, $destination, $allow_overwrite);
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
        foreach ($this->filesystems() as $fs)
            foreach ($fs->directories($glob, $filter) as $dir)
                $directories[$dir->relativePath()][] = $dir;
        return array_values(array_map(
            fn(array $dirs): AggregateDirectory => new AggregateDirectory($this, ...$dirs),
            $directories,
        ));
    }

    /**
     * @inheritDoc
     * 
     * In an AggregateFilesystem this will return an AggregateDirectory that functions much like an AggregateFilesystem. If $create is false the return value will be composed of all existing matches, or null if none were found. If $create is true then the return will be composed of all possible matches, even directories that do not exist yet.
     * 
     * In any AggregateDirectory file creation occurs in the first match.
     * 
     * @return AggregateDirectory|null
     */
    public function directory(string $path, bool $create = false): AggregateDirectory|null
    {
        $directories = [];
        foreach ($this->filesystems() as $fs)
            if ($file = $fs->directory($path, $create))
                $directories[] = $file;
        if (!$directories)
            return null;
        return new AggregateDirectory($this, ...$directories);
    }

    /**
     * @inheritDoc
     * 
     * In an AggregateFilesystem this will return an AggregateFile that mostly functions the same as a normal File, treating the first match as the canonical content for that purpose, but offers a few extra features for concatenating its sub-files.
     * 
     * @return AggregateFile|null
     */
    public function file(string $path, bool $create = false): AggregateFile|null
    {
        $files = [];
        foreach ($this->filesystems() as $fs)
            if ($file = $fs->file($path, $create))
                $files[] = $file;
        if (!$files)
            return null;
        return new AggregateFile($this, ...$files);
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
        foreach ($this->filesystems() as $fs)
            foreach ($fs->files($glob, $filter) as $dir)
                $files[$dir->relativePath()][] = $dir;
        return array_values(array_map(
            fn(array $dirs): AggregateFile => new AggregateFile($this, ...$dirs),
            $files,
        ));
    }

    /**
     * @inheritDoc
     * 
     * @return AggregateDirectory
     */
    public function globDirectory(string $glob, callable|null $filter = null): AggregateDirectory|null
    {
        $directories = $this->directories($glob, $filter);
        return $directories ? $directories[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function globFile(string $glob, callable|null $filter = null): AggregateFile|null
    {
        $files = $this->files($glob, $filter);
        return $files ? $files[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function move(string|FileInterface $source, string|FileInterface $destination, bool $allow_overwrite): void
    {
        if (!$this->contains($source))
            throw new FilesystemException("Source file must be inside AggregateFilesystem: ${source}");
        if (!($destination_fs = $this->parentFilesystem($destination)))
            throw new FilesystemException("Destination file must be inside AggregateFilesystem: ${destination}");
        $destination_fs->moveIn($source, $destination, $allow_overwrite);
    }

    /**
     * @inheritDoc
     */
    public function moveIn(string $source, string|FileInterface $destination, bool $allow_overwrite, bool $allow_uploaded_files = false): void
    {
        if (!($destination_fs = $this->parentFilesystem($destination)))
            throw new FilesystemException("Destination file must be inside AggregateFilesystem: ${destination}");
        $destination_fs->moveIn($source, $destination, $allow_overwrite, $allow_uploaded_files);
    }

    /**
     * @inheritDoc
     */
    public function moveOut(string|FileInterface $source, string $destination, bool $allow_overwrite): void
    {
        if (!($source_fs = $this->parentFilesystem($source)))
            throw new FilesystemException("Source file must be inside AggregateFilesystem: ${source}");
        $source_fs->moveOut($source, $destination, $allow_overwrite);
    }

    /**
     * @inheritDoc
     */
    public function contains(string $path): bool
    {
        foreach ($this->filesystems() as $filesystem)
            if ($filesystem->contains($path))
                return true;
        return false;
    }

    /**
     * Determine which, if any, of this object's filesystems the given path belongs to. If this object contains overlapping filesystems the first match will be returned.
     */
    protected function parentFilesystem(string $path): FilesystemInterface|null
    {
        foreach ($this->filesystems() as $filesystem)
            if ($filesystem->contains($path))
                return $filesystem;
        return null;
    }

    /**
     * Merge all filesystems in priority order
     * 
     * @return Generator<int,FilesystemInterface>
     */
    protected function filesystems(): Generator
    {
        foreach ($this->filesystems_high as $fs)
            yield $fs;
        foreach ($this->filesystems_medium as $fs)
            yield $fs;
        foreach ($this->filesystems_low as $fs)
            yield $fs;
    }

}
