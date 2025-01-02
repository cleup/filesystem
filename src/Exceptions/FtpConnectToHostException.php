<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;

final class FtpConnectToHostException extends RuntimeException implements FtpConnectionExceptionInterface
{
    /**
     * @param string $host
     * @param int $port
     * @param bool $ssl
     * @param string $reason
     * @return static
     */
    public static function forHost($host, $port, $ssl, $reason = '')
    {
        $usingSsl = $ssl ? ', using ssl' : '';

        return new static("Unable to connect to host $host at port $port$usingSsl. $reason");
    }
}
