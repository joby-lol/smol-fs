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
 * Representation of a single file, with utility methods for working with it.
 */
class File implements Stringable
{

    /**
     * @param string $path The full path to the file
     * @internal create from Filesystem instead of instantiating directly
     */
    public function __construct(
        public readonly string $path,
        public readonly string $root,
    ) {}

    /**
     * Write data to the file, replacing any existing content.
     *
     * @throws FilesystemException if writing fails
     */
    public function write(string $data): static
    {
        // Ensure parent directory exists and acquire lock
        FilesystemHelper::recursivelyCreateDirectory(dirname($this->path));
        $handle = fopen($this->path, 'c');
        if ($handle === false)
            throw new FilesystemException("Failed to open file for writing: {$this->path}");
        if (!$this->acquireLock($handle, LOCK_EX)) {
            fclose($handle);
            throw new FilesystemException("Failed to acquire lock for file: {$this->path}");
        }
        // Truncate the file after acquiring lock
        ftruncate($handle, 0);
        // Write data in a loop to handle partial writes
        $remaining = strlen($data);
        $written = 0;
        while ($remaining > 0) {
            $result = fwrite($handle, substr($data, $written));
            if ($result === false)
                throw new FilesystemException("Failed mid-stream to write data to file: {$this->path}");
            $written += $result;
            $remaining -= $result;
        }
        // Unlock and close
        flock($handle, LOCK_UN);
        fclose($handle);
        return $this;
    }

    /**
     * Copy data to the file from an existing file, replacing any existing content.
     */
    public function copyFrom(string $source): static
    {
        // Ensure parent directory exists and acquire lock
        FilesystemHelper::recursivelyCreateDirectory(dirname($this->path));
        $handle = fopen($this->path, 'c');
        if ($handle === false)
            throw new FilesystemException("Failed to open file for writing: {$this->path}");
        if (!$this->acquireLock($handle, LOCK_EX)) {
            fclose($handle);
            throw new FilesystemException("Failed to acquire lock for file: {$this->path}");
        }
        // Open source handle and attempt to acquire a lock
        $source_handle = fopen($source, 'r+');
        if ($source_handle === false)
            throw new FilesystemException("Failed to open file for reading: $source");
        if (!$this->acquireLock($source_handle, LOCK_EX))
            throw new FilesystemException("Failed to acquire lock for file $source");
        // Truncate the file after acquiring both locks
        ftruncate($handle, 0);
        // Write data in a loop to handle partial writes
        $remaining = filesize($source);
        $written = 0;
        while ($remaining > 0) {
            $next_data = fread($source_handle, max($remaining, 8192));
            if ($next_data === false)
                throw new FilesystemException("Failed mid-stream to read data from file: $source");
            $result = fwrite($handle, $next_data);
            if ($result === false)
                throw new FilesystemException("Failed mid-stream to write data to file: {$this->path}");
            $written += $result;
            $remaining -= $result;
        }
        // Unlock and close
        flock($source_handle, LOCK_UN);
        fclose($source_handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $this;
    }

    /**
     * Delete the file if it exists.
     */
    public function delete(): static
    {
        // Delete the file if it exists
        if (is_file($this->path)) {
            if (!unlink($this->path))
                throw new FilesystemException("Failed to delete file: {$this->path}");
        }
        return $this;
    }

    /**
     * Append data to the end of the file.
     *
     * @throws FilesystemException if writing fails
     */
    public function append(string $data): static
    {
        // Ensure file exists and acquire lock
        FilesystemHelper::recursivelyCreateDirectory(dirname($this->path));
        $handle = fopen($this->path, 'a');
        if ($handle === false)
            throw new FilesystemException("Failed to open file for appending: {$this->path}");
        if (!$this->acquireLock($handle, LOCK_EX)) {
            fclose($handle);
            throw new FilesystemException("Failed to acquire lock for file: {$this->path}");
        }
        // Write data in a loop to handle partial writes
        $remaining = strlen($data);
        $written = 0;
        while ($remaining > 0) {
            $result = fwrite($handle, substr($data, $written));
            if ($result === false)
                throw new FilesystemException("Failed mid-stream to append data to file: {$this->path}");
            $written += $result;
            $remaining -= $result;
        }
        // Unlock and close
        flock($handle, LOCK_UN);
        fclose($handle);
        return $this;
    }

    /**
     * Append a line to the end of the file, adding a newline before it if needed.
     * 
     * This method ensures clean line separation without extraneous leading or trailing
     * newlines. If the file has content that doesn't end with a newline, one is added
     * before the new line. The appended line itself has no trailing newline.
     *
     * @throws FilesystemException if writing fails
     */
    public function appendLine(string $line): static
    {
        FilesystemHelper::recursivelyCreateDirectory(dirname($this->path));
        // Open for append and acquire exclusive lock
        $handle = fopen($this->path, 'ab+');
        if ($handle === false)
            throw new FilesystemException("Failed to open file for appending line: {$this->path}");
        if (!$this->acquireLock($handle, LOCK_EX)) {
            fclose($handle);
            throw new FilesystemException("Failed to acquire lock for appending line: {$this->path}");
        }
        // Check if we need a leading newline by reading last byte
        $needsNewline = false;
        $size = filesize($this->path);
        if ($size > 0) {
            fseek($handle, -1, SEEK_END);
            $lastChar = fgetc($handle);
            $needsNewline = $lastChar !== "\n";
        }
        // Prepare the line to write
        if ($needsNewline) {
            $line = "\n" . $line;
        }
        // Write data in a loop to handle partial writes
        $remaining = strlen($line);
        $written = 0;
        while ($remaining > 0) {
            $result = fwrite($handle, substr($line, $written));
            if ($result === false)
                throw new FilesystemException("Failed mid-stream to append line to file: {$this->path}");
            $written += $result;
            $remaining -= $result;
        }
        // unlock and close
        flock($handle, LOCK_UN);
        fclose($handle);
        return $this;
    }

    /**
     * Get the last modified time of the file, or null if the file does not exist.
     * 
     * @throws FilesystemException if getting the modification time fails
     */
    public function modified(): DateTime|null
    {
        if (!$this->exists())
            return null;
        $timestamp = filemtime($this->path);
        if ($timestamp === false)
            throw new FilesystemException("Failed to get modification time for file: {$this->path}");
        return (new DateTime())->setTimestamp($timestamp);
    }

    /**
     * Read the entire contents of the file.
     *
     * @throws FilesystemException if reading fails
     */
    public function read(): string|false
    {
        // if the file does not exist, return false
        if (!$this->exists())
            return false;
        // acquire a file lock and read the contents
        $handle = fopen($this->path, 'r');
        if ($handle === false)
            throw new FilesystemException("Failed to open file for reading: {$this->path}");
        if (!$this->acquireLock($handle, LOCK_SH)) {
            fclose($handle);
            throw new FilesystemException("Failed to acquire lock for file: {$this->path}");
        }
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        // if reading failed, throw an exception
        if ($content === false)
            throw new FilesystemException("Failed to read data from file: {$this->path}");
        // return content
        return $content;
    }

    /**
     * Check if the file exists.
     */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Get the size of the file in bytes, or false if the file does not exist.
     */
    public function size(): int|false
    {
        return $this->exists()
            ? filesize($this->path)
            : false;
    }

    /**
     * Get the filename (basename) of the file.
     */
    public function filename(): string
    {
        return basename($this->path);
    }

    /**
     * Get the file extension, normalized to lower case.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    /**
     * Get the path of the file relative to the root directory.
     */
    public function relativePath(): string
    {
        return substr($this->path, strlen($this->root));
    }

    /**
     * Attempt to acquire a file lock with retries and exponential backoff.
     * 
     * @param resource $handle The file handle
     * @param int $operation The lock operation (LOCK_SH or LOCK_EX)
     * @param int $maxAttempts Maximum number of attempts
     * @param int $initialDelayMs Initial delay in milliseconds
     * @return bool True if lock was acquired, false otherwise
     */
    protected function acquireLock($handle, int $operation, int $maxAttempts = 5, int $initialDelayMs = 10): bool
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // @phpstan-ignore-next-line operation is valid
            if (flock($handle, $operation | LOCK_NB)) {
                return true;
            }
            // Don't sleep on the last attempt
            if ($attempt < $maxAttempts) {
                // Exponential backoff: 10ms, 20ms, 40ms, 80ms
                usleep($initialDelayMs * 1000 * (2 ** ($attempt - 1)));
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->path;
    }

}
