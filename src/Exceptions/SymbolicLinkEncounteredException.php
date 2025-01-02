<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

final class SymbolicLinkEncounteredException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @var string
     */
    private string $path;

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @param string $pathName
     * @return static
     */
    public static function atLocation($pathName)
    {
        $e = new static("Unsupported symbolic link encountered at path $pathName");
        $e->path = $pathName;

        return $e;
    }
}
