<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when an unregistered driver is requested.
 * Used by the file upload library for driver management in the filesystem manager.
 */
class UnregisteredDriverException extends Exception
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