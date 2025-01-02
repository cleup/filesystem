<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface ExtensionToMimeTypeMapInterface
{
    /**
     * @param string $extension
     * @return ?string
     */
    public function lookupMimeType($extension);
}
