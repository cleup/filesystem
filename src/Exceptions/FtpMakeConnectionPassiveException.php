<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when setting passive mode on an FTP connection fails.
 * Used by the file upload library for FTP adapter connections.
 */
class FtpMakeConnectionPassiveException extends RuntimeException implements FtpConnectionExceptionInterface
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