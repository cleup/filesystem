<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use TypeError;
use ValueError;

/**
 * Checks FTP connection liveness by sending a NOOP command.
 * Used by the file upload library to verify connections are still alive before file operations.
 */
class NoopCommandConnectivityChecker implements FtpConnectivityCheckerInterface
{
    /**
     * Test if the FTP connection is still active by sending a NOOP command.
     *
     * @param resource|\FTP\Connection $connection The FTP connection to check.
     * @return bool True if the connection responds with code 200.
     */
    public function isConnected(mixed $connection): bool
    {
        try {
            $response = @ftp_raw($connection, 'NOOP');
        } catch (TypeError | ValueError) {
            return false;
        }

        $responseCode = $response !== null && $response !== []
            ? (int) preg_replace('/\D/', '', implode('', $response))
            : false;

        return $responseCode === 200;
    }
}
