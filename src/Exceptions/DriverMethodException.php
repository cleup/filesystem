<?php

namespace Cleup\Filesystem\Exceptions;

use Exception;

class DriverMethodException extends Exception
{
    /**
     * @param string $method
     * @param int $code
     * @param ?\Throwable $previous
     */
    public function __construct(
        string $method,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            sprintf(
                'Undefined method "%s"',
                $method
            ),
            $code,
            $previous
        );
    }
}
