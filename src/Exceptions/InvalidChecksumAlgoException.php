<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown when an invalid checksum algorithm is specified.
 * Used by the file upload library for file integrity verification.
 */
final class InvalidChecksumAlgoException extends InvalidArgumentException
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