<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when connecting to an FTP host fails.
 * Used by the file upload library for FTP adapter connections.
 */
final class FtpConnectToHostException extends RuntimeException implements FtpConnectionExceptionInterface
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
     * Create an exception for a failed connection to a specific host.
     *
     * @param string $host Hostname or IP address.
     * @param int $port Connection port.
     * @param bool $ssl Whether SSL was used.
     * @param string $reason Additional reason for the failure.
     * @return static
     */
    public static function forHost(string $host, int $port, bool $ssl, string $reason = ''): static
    {
        $usingSsl = $ssl ? ', using ssl' : '';

        return new static("Unable to connect to host $host at port $port$usingSsl. $reason");
    }

    /**
     * Create an exception for a failed connection with a previous exception.
     *
     * @param string $host Hostname or IP address.
     * @param Throwable|null $exception Previous exception.
     * @return static
     */
    public static function atHostname(string $host, ?Throwable $exception = null): static
    {
        return new static("Unable to connect to host $host", 0, $exception);
    }
}