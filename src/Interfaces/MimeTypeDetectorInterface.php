<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface MimeTypeDetectorInterface
{
    /**
     * @param string $path
     * @param string|resource $contents
     * @return ?string
     */
    public function detectMimeType($path, $contents);

    /**
     * @param string $contents
     * @return ?string
     */
    public function detectMimeTypeFromBuffer($contents);

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromPath($path);

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromFile($path);
}
