<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when an FTP LIST response cannot be parsed.
 * Used by the file upload library for FTP adapter directory listings.
 */
class FtpInvalidListResponseReceivedException extends RuntimeException implements FilesystemExceptionInterface
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
