<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a directory creation operation fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
final class CreateDirectoryException extends RuntimeException
{
    private string $path = '';
    private string $reason = '';

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
     * Create an exception for a failed directory creation at a specific path.
     *
     * @param string $dirname The directory path.
     * @param string $errorMessage Additional error message.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function atLocation(string $dirname, string $errorMessage = '', ?Throwable $previous = null): static
    {
        $message = "Unable to create a directory at {$dirname}. {$errorMessage}";
        $e = new static(rtrim($message), 0, $previous);
        $e->path = $dirname;
        $e->reason = $errorMessage;

        return $e;
    }

    /**
     * Create an exception due to a previous failure.
     *
     * @param string $dirname The directory path.
     * @param Throwable $previous Previous exception.
     * @return static
     */
    public static function dueToFailure(string $dirname, Throwable $previous): static
    {
        $reason = $previous instanceof self ? $previous->reason() : '';
        $message = "Unable to create a directory at $dirname. $reason";
        $e = new static(
            rtrim($message),
            0,
            $previous,
        );
        $e->path = $dirname;
        $e->reason = $reason !== '' ? $reason : $message;

        return $e;
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "CREATE_DIRECTORY";
    }

    /**
     * Get the reason for the failure.
     *
     * @return string
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Get the directory path that failed.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }
}
