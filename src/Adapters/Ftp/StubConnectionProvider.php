<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectionProviderInterface;
use Override;

/**
 * Decorator that captures and exposes the created FTP connection for testing purposes.
 * Used by the file upload library to inspect connection state during tests.
 */
class StubConnectionProvider implements FtpConnectionProviderInterface
{
    /** @var resource|\FTP\Connection|null */
    public mixed $connection = null;

    /**
     * @param FtpConnectionProviderInterface $provider The actual connection provider to delegate to.
     */
    public function __construct(
        private readonly FtpConnectionProviderInterface $provider,
    ) {}

    /**
     * Create a connection via the decorated provider and store it for inspection.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @return resource|\FTP\Connection
     */
    #[Override]
    public function createConnection(FtpConnectionOptions $options): mixed
    {
        return $this->connection = $this->provider->createConnection($options);
    }
}