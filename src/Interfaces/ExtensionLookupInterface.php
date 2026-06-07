<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Interface for looking up file extensions by MIME type.
 * Used by the file upload library for MIME type detection and extension mapping.
 */
interface ExtensionLookupInterface
{
    /**
     * Get the primary extension for a MIME type.
     *
     * @param string $mimetype
     * @return string|null
     */
    public function lookupExtension(string $mimetype): ?string;

    /**
     * Get all extensions for a MIME type.
     *
     * @param string $mimetype
     * @return array<int, string>
     */
    public function lookupAllExtensions(string $mimetype): array;
}