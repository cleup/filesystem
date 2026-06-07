<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Adapters\Sftp\SftpAdapter;
use Cleup\Filesystem\Adapters\Sftp\SftpConnectionProvider;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

/**
 * SFTP filesystem driver.
 * Provides SFTP file storage operations through the Driver abstraction.
 */
class SftpDriver extends Driver
{
    /**
     * @inheritDoc
     */
    protected function configure(): ?array
    {
        return [
            'host' => null,
            'username' => null,
            'password' => null,
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
     * @inheritDoc
     */
    protected function create(): AdapterInterface
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
                'preferredAlgorithms',
            ]
        );

        $provider = $this->getConfig('provider')
            ?? SftpConnectionProvider::fromArray($sftpConfig);

        $root = '';

        if ($this->getConfig('root')) {
            $root = rtrim((string) $this->getConfig('root'), '/') . '/';
        }

        return new SftpAdapter(
            connectionProvider: $provider,
            root: $root,
            visibilityConverter: $this->visibilityConverter(),
            mimeTypeDetector: $this->getConfig('mimeTypeDetector'),
            finderMimeTypeDetect: $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}
