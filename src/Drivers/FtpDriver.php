<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Adapters\Ftp\FtpAdapter;
use Cleup\Filesystem\Adapters\Ftp\FtpConnectionOptions;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

use const FTP_BINARY;

/**
 * FTP filesystem driver.
 * Provides FTP/SFTP file storage operations through the Driver abstraction.
 */
class FtpDriver extends Driver
{
    /**
     * @inheritDoc
     */
    protected function configure(): ?array
    {
        return [
            'host' => '',
            'username' => '',
            'password' => '',
            'root' => '',
            'port' => 21,
            'ssl' => false,
            'timeout' => 90,
            'utf8' => false,
            'passive' => true,
            'transferMode' => FTP_BINARY,
            'systemType' => null,
            'useRawListOptions' => null,
            'ignorePassiveAddress' => null,
            'timestampsOnUnixListingsEnabled' => true,
            'recurseManually' => true,
            'provider' => null,
            'checker' => null,
            Filesystem::OPTION_PERMISSIONS => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
            Filesystem::OPTION_VISIBILITY => null,
            Filesystem::OPTION_DIRECTORY_VISIBILITY => null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function create(): AdapterInterface
    {
        $ftpConfig = $this->onlyArrayItems(
            $this->getConfig(),
            [
                'host',
                'username',
                'password',
                'root',
                'port',
                'ssl',
                'timeout',
                'utf8',
                'passive',
                'transferMode',
                'systemType',
                'useRawListOptions',
                'ignorePassiveAddress',
                'timestampsOnUnixListingsEnabled',
                'recurseManually',
            ]
        );

        return new FtpAdapter(
            connectionOptions: FtpConnectionOptions::fromArray($ftpConfig),
            connectionProvider: $this->getConfig('provider'),
            connectivityChecker: $this->getConfig('checker'),
            visibilityConverter: $this->visibilityConverter(),
            mimeTypeDetector: $this->getConfig('mimeTypeDetector'),
            finderMimeTypeDetect: $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}