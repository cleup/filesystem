<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use Cleup\Filesystem\Interfaces\FinderFileAttributesInterface;

/**
 * File attributes value object.
 * Represents a file entry in file listings for the file upload library.
 */
class FileAttributes extends FinderAttributes implements FinderFileAttributesInterface
{
    private string $type = FinderFileAttributesInterface::TYPE_FILE;

    /**
     * @param string $path File path.
     * @param int|null $fileSize File size in bytes.
     * @param string|null $visibility File visibility/permissions.
     * @param int|null $lastModified Last modified timestamp.
     * @param string|null $mimeType MIME type of the file.
     * @param array<string, mixed> $extraMetadata Additional metadata.
     */
    public function __construct(
        private string $path,
        private ?int $fileSize = null,
        private ?string $visibility = null,
        private ?int $lastModified = null,
        private ?string $mimeType = null,
        private array $extraMetadata = [],
    ) {
        $this->path = ltrim($this->path, '/');
    }

    /**
     * Get the file path.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the type (always "file" for files).
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Get the file visibility.
     *
     * @return string|null
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    /**
     * Get the last modified timestamp.
     *
     * @return int|null
     */
    public function lastModified(): ?int
    {
        return $this->lastModified;
    }

    /**
     * Create an instance from an array of attributes.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public static function fromArray(array $attributes): static
    {
        return new static(
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_PATH],
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_FILE_SIZE] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_VISIBILITY] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_LAST_MODIFIED] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_MIME_TYPE] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_EXTRA_METADATA] ?? [],
        );
    }

    /**
     * Check if this is a file (always true for files).
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return true;
    }

    /**
     * Check if this is a directory (always false for files).
     *
     * @return bool
     */
    public function isDir(): bool
    {
        return false;
    }

    /**
     * Create a copy with a different path.
     *
     * @param string $path New path.
     * @return static
     */
    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * Get extra metadata.
     *
     * @return array<string, mixed>
     */
    public function extraMetadata(): array
    {
        return $this->extraMetadata;
    }

    /**
     * Get the file size in bytes.
     *
     * @return int
     */
    public function size(): int
    {
        return $this->fileSize ?? 0;
    }

    /**
     * Get the MIME type.
     *
     * @return string|null
     */
    public function mimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            FinderFileAttributesInterface::ATTRIBUTE_TYPE => self::TYPE_FILE,
            FinderFileAttributesInterface::ATTRIBUTE_PATH => $this->path,
            FinderFileAttributesInterface::ATTRIBUTE_FILE_SIZE => $this->fileSize,
            FinderFileAttributesInterface::ATTRIBUTE_VISIBILITY => $this->visibility,
            FinderFileAttributesInterface::ATTRIBUTE_LAST_MODIFIED => $this->lastModified,
            FinderFileAttributesInterface::ATTRIBUTE_MIME_TYPE => $this->mimeType,
            FinderFileAttributesInterface::ATTRIBUTE_EXTRA_METADATA => $this->extraMetadata,
        ];
    }
}