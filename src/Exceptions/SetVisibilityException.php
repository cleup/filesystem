<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

use function rtrim;

/**
 * Exception thrown when setting file/directory visibility fails.
 * Used by the file upload library for permission setting in local, FTP, and SFTP adapters.
 */
final class SetVisibilityException extends RuntimeException
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
     * Get the reason for the failure.
     *
     * @return string
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Create an exception for a failed visibility change at a specific path.
     *
     * @param string $filename The file or directory path.
     * @param string $extraMessage Additional error message.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function atLocation(string $filename, string $extraMessage = '', ?Throwable $previous = null): static
    {
        $message = "Unable to set visibility for file {$filename}. $extraMessage";
        $e = new static(rtrim($message), 0, $previous);
        $e->reason = $extraMessage;
        $e->path = $filename;

        return $e;
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "SET_VISIBILITY";
    }

    /**
     * Get the file path that failed.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }
}