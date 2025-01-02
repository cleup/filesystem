<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface ExtensionLookupInterface
{
    /**
     * @param string $mimetype
     * @return ?string
     */
    public function lookupExtension($mimetype);

    /**
     * @param string $mimetype
     * @return array
     */
    public function lookupAllExtensions($mimetype);
}
