<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;
use Throwable;

final class FtpResolveConnectionRootException extends RuntimeException implements FtpConnectionExceptionInterface
{
    /**
     * @param string $message
     * @param ?Throwable $previous
     */
    private function __construct($message, $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param string $root
     * @param string $reason
     * @return static
     */
    public static function itDoesNotExist($root, $reason = '')
    {
        return new static(
            'Unable to resolve connection root. It does not seem to exist: ' . $root . "\nreason: $reason"
        );
    }

    /**
     * @param string $message
     * @return static
     */
    public static function couldNotGetCurrentDirectory(string $message = '')
    {
        return new static(
            'Unable to resolve connection root. Could not resolve the current directory. ' . $message
        );
    }
}
