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
use function in_array;

/**
 * Creates and configures FTP connections for the file upload library.
 * Handles authentication, UTF-8 mode, passive mode, and passive address settings.
 */
class FtpConnectionProvider implements FtpConnectionProviderInterface
{
    /**
     * Create a fully configured FTP connection.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @return resource|\FTP\Connection
     * @throws FtpConnectionExceptionInterface
     */
    public function createConnection(FtpConnectionOptions $options): mixed
    {
        $connection = $this->createConnectionResource(
            $options->host(),
            $options->port(),
            $options->timeout(),
            $options->ssl(),
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

    /**
     * Open a raw FTP or FTPS connection to the host.
     *
     * @param string $host Server hostname or IP.
     * @param int $port Connection port.
     * @param int $timeout Timeout in seconds.
     * @param bool $ssl Whether to use SSL/TLS.
     * @return resource|\FTP\Connection
     * @throws FtpConnectToHostException
     */
    private function createConnectionResource(string $host, int $port, int $timeout, bool $ssl): mixed
    {
        error_clear_last();
        $connection = $ssl
            ? @ftp_ssl_connect($host, $port, $timeout)
            : @ftp_connect($host, $port, $timeout);

        if ($connection === false) {
            throw FtpConnectToHostException::forHost($host, $port, $ssl, error_get_last()['message'] ?? '');
        }

        return $connection;
    }

    /**
     * Authenticate with the FTP server using username and password.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @throws FtpAuthenticateException
     */
    private function authenticate(FtpConnectionOptions $options, mixed $connection): void
    {
        if (! @ftp_login($connection, $options->username(), $options->password())) {
            throw new FtpAuthenticateException();
        }
    }

    /**
     * Enable UTF-8 mode on the FTP server if configured.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @throws FtpEnableUtf8ModeException
     */
    private function enableUtf8Mode(FtpConnectionOptions $options, mixed $connection): void
    {
        if (! $options->utf8()) {
            return;
        }

        $response = @ftp_raw($connection, "OPTS UTF8 ON");

        if (! in_array(substr($response[0], 0, 3), ['200', '202'], true)) {
            throw new FtpEnableUtf8ModeException(
                'Could not set UTF-8 mode for connection: ' . $options->host() . '::' . $options->port()
            );
        }
    }

    /**
     * Configure whether to ignore the passive IP address returned by the server.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @throws FtpSetOptionException
     */
    private function ignorePassiveAddress(FtpConnectionOptions $options, mixed $connection): void
    {
        $ignorePassiveAddress = $options->ignorePassiveAddress();

        if (! is_bool($ignorePassiveAddress) || ! defined('FTP_USEPASVADDRESS')) {
            return;
        }

        if (! @ftp_set_option($connection, FTP_USEPASVADDRESS, ! $ignorePassiveAddress)) {
            throw FtpSetOptionException::whileSettingOption('FTP_USEPASVADDRESS');
        }
    }

    /**
     * Set passive or active transfer mode on the connection.
     *
     * @param FtpConnectionOptions $options Connection configuration.
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @throws FtpMakeConnectionPassiveException
     */
    private function makeConnectionPassive(FtpConnectionOptions $options, mixed $connection): void
    {
        if (! @ftp_pasv($connection, $options->passive())) {
            throw new FtpMakeConnectionPassiveException(
                'Could not set passive mode for connection: ' . $options->host() . '::' . $options->port()
            );
        }
    }
}