<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Adapters\Ftp\FtpConnectionOptions;

interface FtpConnectionProviderInterface
{
    /**
     * @param FtpConnectionOptions $options
     * @return resource
     */
    public function createConnection(FtpConnectionOptions $options);
}
