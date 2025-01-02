<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support\MimeType;

use Cleup\Filesystem\Interfaces\ExtensionLookupInterface;
use Cleup\Filesystem\Interfaces\ExtensionToMimeTypeMapInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;

use const PATHINFO_EXTENSION;

class ExtensionMimeTypeDetector implements MimeTypeDetectorInterface, ExtensionLookupInterface
{
    /**
     * @var ExtensionToMimeTypeMapInterface
     */
    private $extensions;

    public function __construct(?ExtensionToMimeTypeMapInterface $extensions = null)
    {
        $this->extensions = $extensions ?: new GeneratedExtensionToMimeTypeMap();
    }

    /**
     * @param string $path
     * @param string|resource $contents
     * @return ?string
     */
    public function detectMimeType($path, $contents)
    {
        return $this->detectMimeTypeFromPath($path);
    }

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromPath($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->extensions->lookupMimeType($extension);
    }

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromFile($path)
    {
        return $this->detectMimeTypeFromPath($path);
    }

    /**
     * @param string $contents
     * @return ?string
     */
    public function detectMimeTypeFromBuffer($contents)
    {
        return null;
    }

    /**
     * @param string $mimetype
     * @return ?string
     */
    public function lookupExtension($mimetype)
    {
        return $this->extensions instanceof ExtensionLookupInterface
            ? $this->extensions->lookupExtension($mimetype)
            : null;
    }

    /**
     * @param string $mimetype
     * @return array
     */
    public function lookupAllExtensions($mimetype)
    {
        return $this->extensions instanceof ExtensionLookupInterface
            ? $this->extensions->lookupAllExtensions($mimetype)
            : [];
    }
}
