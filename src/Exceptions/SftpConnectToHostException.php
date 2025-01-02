<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

class SftpConnectToHostException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @param string $host
     * @param ?Throwable $previous
     * @return static
     */
    public static function atHostname($host, $previous = null)
    {
        return new static(
            "Unable to connect to host: $host",
            0,
            $previous
        );
    }
}
