<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface FtpConnectivityCheckerInterface
{
    public function isConnected($connection): bool;
}
