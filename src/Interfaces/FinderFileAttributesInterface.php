<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Interface for file attributes objects.
 * Extends FinderAttributesInterface with file-specific methods.
 * Used by the file upload library to represent file entries in listings.
 */
interface FinderFileAttributesInterface extends FinderAttributesInterface
{
    /**
     * Get the file size in bytes.
     *
     * @return int
     */
    public function size(): int;

    /**
     * Get the MIME type of the file.
     *
     * @return string|null
     */
    public function mimeType(): ?string;
}