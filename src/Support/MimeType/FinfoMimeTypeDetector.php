<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support\MimeType;

use Cleup\Filesystem\Interfaces\ExtensionLookupInterface;
use Cleup\Filesystem\Interfaces\ExtensionToMimeTypeMapInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use finfo;

use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * MIME type detector using PHP's finfo extension with extension map fallback.
 */
class FinfoMimeTypeDetector implements MimeTypeDetectorInterface, ExtensionLookupInterface
{
    private const INCONCLUSIVE_MIME_TYPES = [
        'application/x-empty',
        'text/plain',
        'text/x-asm',
        'application/octet-stream',
        'inode/x-empty',
    ];

    private finfo $finfo;
    private ExtensionToMimeTypeMapInterface $extensionMap;
    private ?int $bufferSampleSize;

    /** @var array<int, string> */
    private array $inconclusiveMimetypes;

    /**
     * @param string $magicFile Path to magic database file.
     * @param ExtensionToMimeTypeMapInterface|null $extensionMap Extension-to-MIME-type map.
     * @param int|null $bufferSampleSize Maximum bytes to sample from buffer.
     * @param array<int, string> $inconclusiveMimetypes MIME types considered inconclusive.
     */
    public function __construct(
        string $magicFile = '',
        ?ExtensionToMimeTypeMapInterface $extensionMap = null,
        ?int $bufferSampleSize = null,
        array $inconclusiveMimetypes = self::INCONCLUSIVE_MIME_TYPES,
    ) {
        $this->finfo = new finfo(FILEINFO_MIME_TYPE, $magicFile);
        $this->extensionMap = $extensionMap ?? new GeneratedExtensionToMimeTypeMap();
        $this->bufferSampleSize = $bufferSampleSize;
        $this->inconclusiveMimetypes = $inconclusiveMimetypes;
    }

    /**
     * @inheritDoc
     */
    public function detectMimeType(string $path, mixed $contents): ?string
    {
        $mimeType = is_string($contents)
            ? (@$this->finfo->buffer($this->takeSample($contents)) ?: null)
            : null;

        if ($mimeType !== null && ! in_array($mimeType, $this->inconclusiveMimetypes, true)) {
            return $mimeType;
        }

        return $this->detectMimeTypeFromPath($path);
    }

    /**
     * @inheritDoc
     */
    public function detectMimeTypeFromPath(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->extensionMap->lookupMimeType($extension);
    }

    /**
     * @inheritDoc
     */
    public function detectMimeTypeFromFile(string $path): ?string
    {
        return @$this->finfo->file($path) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function detectMimeTypeFromBuffer(string $contents): ?string
    {
        return @$this->finfo->buffer($this->takeSample($contents)) ?: null;
    }

    /**
     * Take a sample from the content buffer if a sample size is configured.
     *
     * @param string $contents
     * @return string
     */
    private function takeSample(string $contents): string
    {
        if ($this->bufferSampleSize === null) {
            return $contents;
        }

        return substr($contents, 0, $this->bufferSampleSize);
    }

    /**
     * @inheritDoc
     */
    public function lookupExtension(string $mimetype): ?string
    {
        return $this->extensionMap instanceof ExtensionLookupInterface
            ? $this->extensionMap->lookupExtension($mimetype)
            : null;
    }

    /**
     * @inheritDoc
     */
    public function lookupAllExtensions(string $mimetype): array
    {
        return $this->extensionMap instanceof ExtensionLookupInterface
            ? $this->extensionMap->lookupAllExtensions($mimetype)
            : [];
    }
}
