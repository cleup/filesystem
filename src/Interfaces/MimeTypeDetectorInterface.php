<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Detects MIME types for files.
 */
interface MimeTypeDetectorInterface
{
    /**
     * @param string $path
     * @param string|resource $contents
     * @return string|null
     */
    public function detectMimeType(string $path, mixed $contents): ?string;

    /**
     * @param string $contents
     * @return string|null
     */
    public function detectMimeTypeFromBuffer(string $contents): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function detectMimeTypeFromPath(string $path): ?string;

    /**
     * @param string $path
     * @return string|null
     */
    public function detectMimeTypeFromFile(string $path): ?string;
}
