<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use ValueError;

class RawListFtpConnectivityChecker implements FtpConnectivityCheckerInterface
{
    /**
     * @inheritDoc
     */
    public function isConnected($connection): bool
    {
        try {
            return $connection !== false && @ftp_rawlist($connection, './') !== false;
        } catch (ValueError $errror) {
            return false;
        }
    }
}
