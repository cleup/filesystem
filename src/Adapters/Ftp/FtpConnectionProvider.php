<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Exceptions\FtpAuthenticateException;
use Cleup\Filesystem\Exceptions\FtpConnectToHostException;
use Cleup\Filesystem\Exceptions\FtpEnableUtf8ModeException;
use Cleup\Filesystem\Exceptions\FtpMakeConnectionPassiveException;
use Cleup\Filesystem\Exceptions\FtpSetOptionException;
use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use Cleup\Filesystem\Interfaces\FtpConnectionProviderInterface;

use const FTP_USEPASVADDRESS;
use function error_clear_last;
use function error_get_last;

class FtpConnectionProvider implements FtpConnectionProviderInterface
{
    /**
     * @return resource
     *
     * @throws FtpConnectionExceptionInterface
     */
    public function createConnection(FtpConnectionOptions $options)
    {
        $connection = $this->createConnectionResource(
            $options->host(),
            $options->port(),
            $options->timeout(),
            $options->ssl()
        );

        try {
            $this->authenticate($options, $connection);
            $this->enableUtf8Mode($options, $connection);
            $this->ignorePassiveAddress($options, $connection);
            $this->makeConnectionPassive($options, $connection);
        } catch (FtpConnectionExceptionInterface $exception) {
            @ftp_close($connection);
            throw $exception;
        }

        return $connection;
    }


    private function createConnectionResource(string $host, int $port, int $timeout, bool $ssl)
    {
        error_clear_last();
        $connection = $ssl ? @ftp_ssl_connect($host, $port, $timeout) : @ftp_connect($host, $port, $timeout);

        if ($connection === false) {
            throw FtpConnectToHostException::forHost($host, $port, $ssl, error_get_last()['message'] ?? '');
        }

        return $connection;
    }


    private function authenticate(FtpConnectionOptions $options, $connection): void
    {
        if (! @ftp_login($connection, $options->username(), $options->password())) {
            throw new FtpAuthenticateException();
        }
    }

    private function enableUtf8Mode(FtpConnectionOptions $options, $connection): void
    {
        if (! $options->utf8()) {
            return;
        }

        $response = @ftp_raw($connection, "OPTS UTF8 ON");

        if (! in_array(substr($response[0], 0, 3), ['200', '202'])) {
            throw new FtpEnableUtf8ModeException(
                'Could not set UTF-8 mode for connection: ' . $options->host() . '::' . $options->port()
            );
        }
    }


    private function ignorePassiveAddress(FtpConnectionOptions $options, $connection): void
    {
        $ignorePassiveAddress = $options->ignorePassiveAddress();

        if (! is_bool($ignorePassiveAddress) || ! defined('FTP_USEPASVADDRESS')) {
            return;
        }

        if (! @ftp_set_option($connection, FTP_USEPASVADDRESS, ! $ignorePassiveAddress)) {
            throw FtpSetOptionException::whileSettingOption('FTP_USEPASVADDRESS');
        }
    }


    private function makeConnectionPassive(FtpConnectionOptions $options, $connection): void
    {
        if (! @ftp_pasv($connection, $options->passive())) {
            throw new FtpMakeConnectionPassiveException(
                'Could not set passive mode for connection: ' . $options->host() . '::' . $options->port()
            );
        }
    }
}
