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
 * Representation of a single file, with utility methods for working with it.
 */
interface FileInterface extends Stringable
{

    /**
     * Write data to the file, replacing any existing content.
     *
     * @throws FilesystemException if writing fails
     */
    public function write(string $data): static;

    /**
     * Copy data to the file from an existing file, replacing any existing content.
     */
    public function copyFrom(string $source): static;

    /**
     * Delete the file if it exists.
     */
    public function delete(): static;

    /**
     * Append data to the end of the file.
     *
     * @throws FilesystemException if writing fails
     */
    public function append(string $data): static;

    /**
     * Append a line to the end of the file, adding a newline before it if needed.
     * 
     * This method ensures clean line separation without extraneous leading or trailing
     * newlines. If the file has content that doesn't end with a newline, one is added
     * before the new line. The appended line itself has no trailing newline.
     *
     * @throws FilesystemException if writing fails
     */
    public function appendLine(string $line): static;

    /**
     * Get the last modified time of the file, or null if the file does not exist.
     * 
     * @throws FilesystemException if getting the modification time fails
     */
    public function modified(): DateTimeImmutable|null;

    /**
     * Read the entire contents of the file.
     *
     * @throws FilesystemException if reading fails
     */
    public function read(): string|false;

    /**
     * Check if the file exists.
     */
    public function exists(): bool;

    /**
     * Get the size of the file in bytes, or false if the file does not exist.
     */
    public function size(): int|false;

    /**
     * Get the filename (basename) of the file.
     */
    public function filename(): string;

    /**
     * Get the file extension, normalized to lower case.
     */
    public function extension(): string;

    /**
     * Get the path of the file relative to the root directory.
     */
    public function relativePath(): string;

}
