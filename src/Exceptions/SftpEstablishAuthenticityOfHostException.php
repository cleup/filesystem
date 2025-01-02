<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

class SftpEstablishAuthenticityOfHostException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @param string $host
     * @return static
     */
    public static function becauseTheAuthenticityCantBeEstablished($host)
    {
        return new static("The authenticity of host $host can't be established.");
    }
}
