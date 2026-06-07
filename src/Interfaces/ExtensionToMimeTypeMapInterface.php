<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Maps file extensions to MIME types.
 */
interface ExtensionToMimeTypeMapInterface
{
    public function lookupMimeType(string $extension): ?string;
}