<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use phpseclib3\Net\SFTP;

/**
 * Checks whether an SFTP connection is still alive.
 */
interface SftpConnectivityCheckerInterface
{
    public function isConnected(SFTP $connection): bool;
}