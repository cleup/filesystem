<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when a symbolic link is encountered and not allowed.
 * Used by the file upload library for local adapter when link handling is set to DISALLOW_LINKS.
 */
final class SymbolicLinkEncounteredException extends RuntimeException implements FilesystemExceptionInterface
{
    private string $path = '';

    /**
     * @param string $message Error message.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the path of the symbolic link.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Create an exception for a symbolic link at a specific path.
     *
     * @param string $pathName The path where the symbolic link was encountered.
     * @return static
     */
    public static function atLocation(string $pathName): static
    {
        $e = new static("Unsupported symbolic link encountered at path $pathName");
        $e->path = $pathName;

        return $e;
    }
}