<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Adapters\Ftp\FtpConnectionOptions;

/**
 * Creates FTP connections from configuration.
 */
interface FtpConnectionProviderInterface
{
    /**
     * @param FtpConnectionOptions $options
     * @return resource|\FTP\Connection
     */
    public function createConnection(FtpConnectionOptions $options): mixed;
}