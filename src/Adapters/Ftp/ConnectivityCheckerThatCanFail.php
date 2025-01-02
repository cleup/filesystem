<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;

class ConnectivityCheckerThatCanFail implements FtpConnectivityCheckerInterface
{
    private bool $failNextCall = false;

    public function __construct(private FtpConnectivityCheckerInterface $connectivityChecker)
    {
    }

    public function failNextCall(): void
    {
        $this->failNextCall = true;
    }

    /**
     * @inheritDoc
     */
    public function isConnected($connection): bool
    {
        if ($this->failNextCall) {
            $this->failNextCall = false;

            return false;
        }

        return $this->connectivityChecker->isConnected($connection);
    }
}
