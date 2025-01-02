<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support\MimeType;

use Cleup\Filesystem\Interfaces\ExtensionLookupInterface;
use Cleup\Filesystem\Interfaces\ExtensionToMimeTypeMapInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use finfo;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

class FinfoMimeTypeDetector implements MimeTypeDetectorInterface, ExtensionLookupInterface
{
    /**
     * @var array
     */
    private const INCONCLUSIVE_MIME_TYPES = [
        'application/x-empty',
        'text/plain',
        'text/x-asm',
        'application/octet-stream',
        'inode/x-empty',
    ];

    /**
     * @var finfo
     */
    private $finfo;

    /**
     * @var ExtensionToMimeTypeMapInterface
     */
    private $extensionMap;

    /**
     * @var int|null
     */
    private $bufferSampleSize;

    /**
     * @var array<string>
     */
    private $inconclusiveMimetypes;

    public function __construct(
        string $magicFile = '',
        ?ExtensionToMimeTypeMapInterface $extensionMap = null,
        ?int $bufferSampleSize = null,
        array $inconclusiveMimetypes = self::INCONCLUSIVE_MIME_TYPES
    ) {
        $this->finfo = new finfo(FILEINFO_MIME_TYPE, $magicFile);
        $this->extensionMap = $extensionMap ?: new GeneratedExtensionToMimeTypeMap();
        $this->bufferSampleSize = $bufferSampleSize;
        $this->inconclusiveMimetypes = $inconclusiveMimetypes;
    }

    /**
     * @param string $path
     * @param string|resource $contents
     * @return ?string
     */
    public function detectMimeType($path, $contents)
    {
        $mimeType = is_string($contents)
            ? (@$this->finfo->buffer($this->takeSample($contents)) ?: null)
            : null;

        if ($mimeType !== null && ! in_array($mimeType, $this->inconclusiveMimetypes)) {
            return $mimeType;
        }

        return $this->detectMimeTypeFromPath($path);
    }

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromPath($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->extensionMap->lookupMimeType($extension);
    }

    /**
     * @param string $path
     * @return ?string
     */
    public function detectMimeTypeFromFile($path)
    {
        return @$this->finfo->file($path) ?: null;
    }

    /**
     * @param string $contents
     * @return ?string
     */
    public function detectMimeTypeFromBuffer($contents)
    {
        return @$this->finfo->buffer($this->takeSample($contents)) ?: null;
    }

    private function takeSample(string $contents): string
    {
        if ($this->bufferSampleSize === null) {
            return $contents;
        }

        return (string) substr($contents, 0, $this->bufferSampleSize);
    }

    /**
     * @param string $mimetype
     * @return ?string
     */
    public function lookupExtension($mimetype)
    {
        return $this->extensionMap instanceof ExtensionLookupInterface
            ? $this->extensionMap->lookupExtension($mimetype)
            : null;
    }

    /**
     * @param string $mimetype
     * @return array
     */
    public function lookupAllExtensions($mimetype)
    {
        return $this->extensionMap instanceof ExtensionLookupInterface
            ? $this->extensionMap->lookupAllExtensions($mimetype)
            : [];
    }
}
