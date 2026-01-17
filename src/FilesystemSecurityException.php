<?php

/**
 * smolFS
 * https://github.com/joby-lol/smol-fs
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Filesystem;

/**
 * Exception indicating that a security violation occurred within the Filesystem abstraction, such as a directory traversal attack.
 */
class FilesystemSecurityException extends FilesystemException
{

}
