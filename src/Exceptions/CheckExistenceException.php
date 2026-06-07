<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a file/directory existence check fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
class CheckExistenceException extends RuntimeException
{
    /**
     * @param string $message Error message.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     */
    final public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a specific path.
     *
     * @param string $path The path that failed the check.
     * @param Throwable|null $exception Previous exception.
     * @return static
     */
    public static function forLocation(string $path, ?Throwable $exception = null): static
    {
        return new static(
            "Unable to check existence for: {$path}",
            0,
            $exception,
        );
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "EXISTENCE_CHECK";
    }
}
