<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use phpseclib3\Net\SFTP;

/**
 * Provides and manages SFTP connections.
 */
interface SftpConnectionProviderInterface
{
    public function provideConnection(): SFTP;

    public function disconnect(): void;
}