<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Adapters\Local\LocalAdapter;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

use const LOCK_EX;

/**
 * Local filesystem driver.
 * Provides local file storage operations through the Driver abstraction.
 */
class LocalDriver extends Driver
{
    /**
     * @inheritDoc
     */
    protected function configure(): ?array
    {
        return [
            'root' => '',
            'lock' => false,
            'mimeTypeDetector' => null,
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
            'links' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function create(): AdapterInterface
    {
        $links = $this->getConfig('links') === 'skip'
            ? LocalAdapter::SKIP_LINKS
            : LocalAdapter::DISALLOW_LINKS;

        $lock = $this->getConfig('lock', false) ? LOCK_EX : 0;

        return new LocalAdapter(
            location: $this->getConfig('root'),
            visibility: $this->visibilityConverter(),
            writeFlags: $lock,
            linkHandling: $links,
            mimeTypeDetector: $this->getConfig('mimeTypeDetector'),
            finderMimeTypeDetect: $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}