<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use TypeError;
use ValueError;

class NoopCommandConnectivityChecker implements FtpConnectivityCheckerInterface
{
    public function isConnected($connection): bool
    {
        // @codeCoverageIgnoreStart
        try {
            $response = @ftp_raw($connection, 'NOOP');
        } catch (TypeError | ValueError $typeError) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        $responseCode = $response ? (int) preg_replace('/\D/', '', implode('', $response)) : false;

        return $responseCode === 200;
    }
}
