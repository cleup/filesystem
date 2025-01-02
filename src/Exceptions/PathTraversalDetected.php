<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

class PathTraversalDetectedException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @var string
     */
    private $path;

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return static
     */
    public static function forPath($path)
    {
        $e = new static("Path traversal detected: {$path}");
        $e->path = $path;

        return $e;
    }
}
