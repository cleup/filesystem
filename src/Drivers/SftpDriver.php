<?php

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Adapters\Sftp\SftpAdapter;
use Cleup\Filesystem\Adapters\Sftp\SftpConnectionProvider;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

class SftpDriver extends Driver
{
    /**
     * List of default configuration options
     *
     * @return array
     */
    protected function configure()
    {
        return [
            'host' => null,       // required
            'username' => null,   // required
            'password' => null,   // required
            'privateKey' => null,
            'passphrase' => null,
            'useAgent' => false,
            'root' => '',
            'port' => 22,
            'timeout' => 30,
            'maxTries' => 4,
            'hostFingerprint' => null,
            'connectivityChecker' => null,
            'preferredAlgorithms' => [],
            'provider' => null,
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
        $sftpConfig = $this->onlyArrayItems(
            $this->getConfig(),
            [
                'host',
                'username',
                'password',
                'privateKey',
                'passphrase',
                'useAgent',
                'port',
                'timeout',
                'maxTries',
                'hostFingerprint',
                'connectivityChecker',
                'preferredAlgorithms'
            ]
        );

        $provider = $this->getConfig(
            'provider',
            SftpConnectionProvider::fromArray($sftpConfig)
        );

        $root = '';

        if ($this->getConfig('root', false))
            $root = rtrim($this->getConfig('root'), '/') . '/';

        return new SftpAdapter(
            $provider,
            $root,
            $this->visibilityConverter(),
            $this->getConfig('mimeTypeDetector', null),
            $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}
