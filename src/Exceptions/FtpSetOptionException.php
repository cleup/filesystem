<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when setting an FTP option fails.
 * Used by the file upload library for FTP adapter connections.
 */
class FtpSetOptionException extends RuntimeException implements FtpConnectionExceptionInterface
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
     * Create an exception for a failed option setting.
     *
     * @param string $option The option that failed to set.
     * @return static
     */
    public static function whileSettingOption(string $option): static
    {
        return new static("Unable to set FTP option $option.");
    }
}