<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use phpseclib3\Net\SFTP;

/**
 * @method void disconnect()
 */
interface SftpConnectionProviderInterface
{
    public function provideConnection(): SFTP;
}
