<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown when an invalid stream is provided to a write operation.
 * Used by the file upload library for stream validation in local, FTP, and SFTP adapters.
 */
class InvalidStreamProvidedException extends InvalidArgumentException implements FilesystemExceptionInterface
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
}
