<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when an undefined driver method is called.
 * Used by the file upload library to indicate missing driver functionality.
 */
class DriverMethodException extends Exception
{
    /**
     * @param string $method The undefined method name.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $method,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Undefined method "%s"', $method),
            $code,
            $previous,
        );
    }
}