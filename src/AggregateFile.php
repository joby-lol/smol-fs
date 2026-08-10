<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

use DateTimeImmutable;

class AggregateFile implements FileInterface
{

    /**
     * Internal array of files to query, in descending precedence order. Writes/creations will occur in the first one by default.
     * @var array<FileInterface>
     */
    protected array $files = [];

    public function __construct(
        protected AggregateFilesystem $root,
        FileInterface $main_file,
        FileInterface ...$files,
    )
    {
        $this->files = [$main_file, ...$files];
    }

    /**
     * @inheritDoc
     */
    public function append(string $data): static
    {
        $this->files[0]->append($data);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function appendLine(string $line): static
    {
        $this->files[0]->appendLine($line);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function copyFrom(string $source): static
    {
        $this->files[0]->copyFrom($source);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function delete(): static
    {
        foreach ($this->files as $file)
            $file->delete();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function exists(): bool
    {
        return $this->files[0]->exists();
    }

    /**
     * @inheritDoc
     */
    public function extension(): string
    {
        return $this->files[0]->extension();
    }

    /**
     * @inheritDoc
     */
    public function filename(): string
    {
        return $this->files[0]->filename();
    }

    /**
     * @inheritDoc
     */
    public function modified(): DateTimeImmutable|null
    {
        return $this->files[0]->modified();
    }

    /**
     * @inheritDoc
     */
    public function read(): string|false
    {
        return $this->files[0]->read();
    }

    /**
     * @inheritDoc
     */
    public function relativePath(): string
    {
        return $this->files[0]->relativePath();
    }

    /**
     * @inheritDoc
     */
    public function size(): int|false
    {
        return $this->files[0]->size();
    }

    /**
     * @inheritDoc
     */
    public function write(string $data): static
    {
        $this->files[0]->write($data);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->files[0]->__tostring();
    }

}
