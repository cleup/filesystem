<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when connecting to an SFTP host fails.
 * Used by the file upload library for SFTP adapter connections.
 */
class SftpConnectToHostException extends RuntimeException implements FilesystemExceptionInterface
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
     * Create an exception for a failed connection to a specific host.
     *
     * @param string $host Hostname or IP address.
     * @param Throwable|null $previous Previous exception.
     * @return static
     */
    public static function atHostname(string $host, ?Throwable $previous = null): static
    {
        return new static(
            "Unable to connect to host: $host",
            0,
            $previous,
        );
    }
}