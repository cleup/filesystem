<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a directory listing operation fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
final class FinderException extends RuntimeException
{
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
     * Create an exception for a failed directory listing operation.
     *
     * @param string $path The directory path.
     * @param bool $deep Whether the listing was recursive.
     * @param Throwable $previous Previous exception.
     * @return static
     */
    public static function atLocation(string $path, bool $deep, Throwable $previous): static
    {
        $message = "Unable to list contents for '$path', " . ($deep ? 'deep' : 'shallow') . " listing\n\n"
            . 'Reason: ' . $previous->getMessage();

        return new static($message, 0, $previous);
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "FINDER";
    }
}
