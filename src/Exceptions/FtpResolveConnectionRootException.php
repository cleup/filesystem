<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when resolving the FTP connection root directory fails.
 * Used by the file upload library for FTP adapter connections.
 */
final class FtpResolveConnectionRootException extends RuntimeException implements FtpConnectionExceptionInterface
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
     * Create an exception when the connection root does not exist.
     *
     * @param string $root The root directory path.
     * @param string $reason Additional reason for the failure.
     * @return static
     */
    public static function itDoesNotExist(string $root, string $reason = ''): static
    {
        return new static(
            'Unable to resolve connection root. It does not seem to exist: ' . $root . "\nreason: $reason"
        );
    }

    /**
     * Create an exception when the current directory cannot be determined.
     *
     * @param string $message Additional error message.
     * @return static
     */
    public static function couldNotGetCurrentDirectory(string $message = ''): static
    {
        return new static(
            'Unable to resolve connection root. Could not resolve the current directory. ' . $message
        );
    }
}
