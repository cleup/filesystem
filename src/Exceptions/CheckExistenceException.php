<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

class CheckExistenceException extends RuntimeException
{
    /**
     * @param string $message 
     * @param int $code
     * @param ?Throwable $previous
     */
    final public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param string $path 
     * @param ?Throwable $previous
     * @return static
     */
    public static function forLocation(string $path, $exception = null)
    {
        return new static(
            "Unable to check existence for: {$path}",
            0,
            $exception
        );
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "EXISTENCE_CHECK";
    }
}
