<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Finder\FileAttributes;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when retrieving file metadata fails.
 * Used by the file upload library for local, FTP, and SFTP adapters.
 */
final class RetrieveMetadataException extends RuntimeException
{
    private string $path = '';
    private string $metadataType = '';
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
     * Create an exception for a failed last modified retrieval.
     *
     * @param string $path The file path.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function lastModified(string $path, string $reason = '', ?Throwable $previous = null): static
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_LAST_MODIFIED,
            $reason,
            $previous,
        );
    }

    /**
     * Create an exception for a failed visibility retrieval.
     *
     * @param string $path The file path.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function getVisibility(string $path, string $reason = '', ?Throwable $previous = null): static
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_VISIBILITY,
            $reason,
            $previous,
        );
    }

    /**
     * Create an exception for a failed file size retrieval.
     *
     * @param string $path The file path.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function size(string $path, string $reason = '', ?Throwable $previous = null): static
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_FILE_SIZE,
            $reason,
            $previous,
        );
    }

    /**
     * Create an exception for a failed MIME type retrieval.
     *
     * @param string $path The file path.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function mimeType(string $path, string $reason = '', ?Throwable $previous = null): static
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_MIME_TYPE,
            $reason,
            $previous,
        );
    }

    /**
     * Create an exception for a failed metadata retrieval of any type.
     *
     * @param string $path The file path.
     * @param string $type The metadata type being retrieved.
     * @param string $reason Additional reason for the failure.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function create(string $path, string $type, string $reason = '', ?Throwable $previous = null): static
    {
        $e = new static("Unable to retrieve the $type for file at path: $path. {$reason}", 0, $previous);
        $e->reason = $reason;
        $e->path = $path;
        $e->metadataType = $type;

        return $e;
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
     * Get the file path that failed.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the metadata type that was being retrieved.
     *
     * @return string
     */
    public function metadataType(): string
    {
        return $this->metadataType;
    }

    /**
     * Get the operation type for this exception.
     *
     * @return string
     */
    public function operation(): string
    {
        return "RETRIEVE_METADATA";
    }
}
