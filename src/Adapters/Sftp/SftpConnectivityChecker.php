<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Interfaces\SftpConnectivityCheckerInterface;
use phpseclib3\Net\SFTP;
use Throwable;

class SftpConnectivityChecker implements SftpConnectivityCheckerInterface
{
    public function __construct(
        private bool $usePing = false,
    ) {
    }

    public static function create(): SftpConnectivityChecker
    {
        return new SftpConnectivityChecker();
    }

    public function withUsingPing(bool $usePing): SftpConnectivityChecker
    {
        $clone = clone $this;
        $clone->usePing = $usePing;

        return $clone;
    }

    public function isConnected(SFTP $connection): bool
    {
        if ( ! $connection->isConnected()) {
            return false;
        }

        if ( ! $this->usePing) {
            return true;
        }

        try {
            return $connection->ping();
        } catch (Throwable) {
            return false;
        }
    }
}
