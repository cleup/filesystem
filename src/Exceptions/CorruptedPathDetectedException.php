<?php

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

final class CorruptedPathDetectedException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @param string $path
     */
    public static function forPath($path)
    {
        return new static("Corrupted path detected: " . $path);
    }
}
