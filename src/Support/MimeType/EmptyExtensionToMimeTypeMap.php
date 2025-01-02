<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support\MimeType;

use Cleup\Filesystem\Interfaces\ExtensionToMimeTypeMapInterface;

class EmptyExtensionToMimeTypeMap implements ExtensionToMimeTypeMapInterface
{
    /**
     * @param string $extension
     * @return ?string
     */
    public function lookupMimeType($extension)
    {
        return null;
    }
}
