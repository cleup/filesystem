<?php

namespace Cleup\Filesystem\Drivers;

use Cleup\Filesystem\Adapters\Local\LocalAdapter;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\AdapterInterface;

class LocalDriver extends Driver
{
    /**
     * List of default configuration options
     *
     * @return array
     */
    protected function configure()
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
            'links' => null
        ];
    }

    /**
     * Create an instance of the local driver.
     *
     * @param array $config
     * @return AdapterInterface
     */
    protected function create()
    {
        $links = $this->getConfig('links') === 'skip'
            ? LocalAdapter::SKIP_LINKS
            : LocalAdapter::DISALLOW_LINKS;

        $lock = $this->getConfig('lock', false) ? LOCK_EX : 0;

        return new LocalAdapter(
            $this->getConfig('root'),
            $this->visibilityConverter(),
            $lock,
            $links,
            $this->getConfig('mimeTypeDetector'),
            $this->getConfig('finderMimeTypeDetect', false),
        );
    }
}
