<?php

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Adapters\Ftp\FtpAdapter;
use Cleup\Filesystem\Adapters\Ftp\FtpConnectionOptions;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

class FtpDriver extends Driver
{
    /**
     * List of default configuration options
     *
     * @return array
     */
    protected function configure()
    {
        return [
            'host' => '', // required
            'username' => '', // required
            'password' => '', // required
            'root' => '',
            'port' => 21,
            'ssl' => false,
            'timeout' => 90,
            'utf8' => false,
            'passive' => true,
            'transferMode' => FTP_BINARY,
            'systemType' => null, // 'windows' or 'unix'
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
     * Create an instance of the ftp driver.
     *
     * @return AdapterInterface
     */
    protected function create()
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
            FtpConnectionOptions::fromArray($ftpConfig),
            $this->getConfig('provider', null),
            $this->getConfig('checker', null),
            $this->visibilityConverter(),
            $this->getConfig('mimeTypeDetector', null),
            $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}
