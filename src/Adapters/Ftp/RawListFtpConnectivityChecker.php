<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use Override;
use ValueError;

/**
 * Checks FTP connection liveness by attempting to list the current directory.
 * Used by the file upload library as a fallback connectivity check when NOOP is unavailable.
 */
class RawListFtpConnectivityChecker implements FtpConnectivityCheckerInterface
{
    /**
     * Test if the FTP connection is still active by listing the current directory.
     *
     * @param resource|\FTP\Connection|false $connection The FTP connection to check.
     * @return bool True if the directory listing succeeds.
     */
    #[Override]
    public function isConnected(mixed $connection): bool
    {
        try {
            return $connection !== false && @ftp_rawlist($connection, './') !== false;
        } catch (ValueError) {
            return false;
        }
    }
}
