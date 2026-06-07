<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Checks whether an FTP connection is still alive.
 */
interface FtpConnectivityCheckerInterface
{
    /**
     * @param resource|\FTP\Connection $connection
     * @return bool
     */
    public function isConnected(mixed $connection): bool;
}