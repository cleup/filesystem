<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use Cleup\Filesystem\Interfaces\FinderAttributesInterface;

/**
 * Directory attributes value object.
 * Represents a directory entry in file listings for the file upload library.
 */
class DirectoryAttributes extends FinderAttributes implements FinderAttributesInterface
{
    private string $type = FinderAttributesInterface::TYPE_DIRECTORY;

    /**
     * @param string $path Directory path.
     * @param string|null $visibility Directory visibility/permissions.
     * @param int|null $lastModified Last modified timestamp.
     * @param array<string, mixed> $extraMetadata Additional metadata.
     */
    public function __construct(
        private string $path,
        private ?string $visibility = null,
        private ?int $lastModified = null,
        private array $extraMetadata = [],
    ) {
        $this->path = trim($this->path, '/');
    }

    /**
     * Get the directory path.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get the type (always "dir" for directories).
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Get the directory visibility.
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
            $attributes[FinderAttributesInterface::ATTRIBUTE_PATH],
            $attributes[FinderAttributesInterface::ATTRIBUTE_VISIBILITY] ?? null,
            $attributes[FinderAttributesInterface::ATTRIBUTE_LAST_MODIFIED] ?? null,
            $attributes[FinderAttributesInterface::ATTRIBUTE_EXTRA_METADATA] ?? [],
        );
    }

    /**
     * Check if this is a file (always false for directories).
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return false;
    }

    /**
     * Check if this is a directory (always true for directories).
     *
     * @return bool
     */
    public function isDir(): bool
    {
        return true;
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
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            FinderAttributesInterface::ATTRIBUTE_TYPE => $this->type,
            FinderAttributesInterface::ATTRIBUTE_PATH => $this->path,
            FinderAttributesInterface::ATTRIBUTE_VISIBILITY => $this->visibility,
            FinderAttributesInterface::ATTRIBUTE_LAST_MODIFIED => $this->lastModified,
            FinderAttributesInterface::ATTRIBUTE_EXTRA_METADATA => $this->extraMetadata,
        ];
    }
}
