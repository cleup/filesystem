<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a directory deletion operation fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
final class DeleteDirectoryException extends RuntimeException
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
     * Create an exception for a failed directory deletion at a specific path.
     *
     * @param string $path The directory path.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function atLocation(string $path, string $reason = '', ?Throwable $previous = null): static
    {
        $e = new static(
            rtrim("Unable to delete directory located at: {$path}. {$reason}"),
            0,
            $previous,
        );
        $e->path = $path;
        $e->reason = $reason;

        return $e;
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "DELETE_DIRECTORY";
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