<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use phpseclib3\Net\SFTP;

interface SftpConnectivityCheckerInterface
{
    public function isConnected(SFTP $connection): bool;
}
