<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a file move operation fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
final class MoveFileException extends RuntimeException
{
    private string $from = '';
    private string $to = '';

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
     * Create an exception when source and destination paths are identical.
     *
     * @param string $from Source path.
     * @param string $to Destination path.
     * @return static
     */
    public static function fromAndToAreTheSame(string $from, string $to): static
    {
        return static::because('Source and destination are the same', $from, $to);
    }

    /**
     * Get the source path.
     *
     * @return string
     */
    public function from(): string
    {
        return $this->from;
    }

    /**
     * Get the destination path.
     *
     * @return string
     */
    public function to(): string
    {
        return $this->to;
    }

    /**
     * Create an exception for a failed move from one path to another.
     *
     * @param string $fromPath Source path.
     * @param string $toPath Destination path.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function fromLocationTo(string $fromPath, string $toPath, ?Throwable $previous = null): static
    {
        $message = $previous?->getMessage() ?? "Unable to move file from $fromPath to $toPath";
        $e = new static($message, 0, $previous);
        $e->from = $fromPath;
        $e->to = $toPath;

        return $e;
    }

    /**
     * Create an exception with a specific reason.
     *
     * @param string $reason Reason for the failure.
     * @param string $fromPath Source path.
     * @param string $toPath Destination path.
     * @return static
     */
    public static function because(string $reason, string $fromPath, string $toPath): static
    {
        $message = "Unable to move file from $fromPath to $toPath, because $reason";
        $e = new static($message);
        $e->from = $fromPath;
        $e->to = $toPath;

        return $e;
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "MOVE";
    }
}