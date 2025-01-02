<?php
declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectionProviderInterface;

class StubConnectionProvider implements FtpConnectionProviderInterface
{
    public mixed $connection;

    public function __construct(private FtpConnectionProviderInterface $provider)
    {
    }

    public function createConnection(FtpConnectionOptions $options)
    {
        return $this->connection = $this->provider->createConnection($options);
    }
}
