<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use ArrayAccess;
use JsonSerializable;

/**
 * Interface for file/directory attributes objects.
 * Used by the file upload library to represent filesystem entries in listings.
 */
interface FinderAttributesInterface extends JsonSerializable, ArrayAccess
{
    /** @var string */
    public const ATTRIBUTE_PATH = 'path';

    /** @var string */
    public const ATTRIBUTE_TYPE = 'type';

    /** @var string */
    public const ATTRIBUTE_FILE_SIZE = 'file_size';

    /** @var string */
    public const ATTRIBUTE_VISIBILITY = 'visibility';

    /** @var string */
    public const ATTRIBUTE_LAST_MODIFIED = 'last_modified';

    /** @var string */
    public const ATTRIBUTE_MIME_TYPE = 'mime_type';

    /** @var string */
    public const ATTRIBUTE_EXTRA_METADATA = 'extra_metadata';

    /** @var string */
    public const TYPE_FILE = 'file';

    /** @var string */
    public const TYPE_DIRECTORY = 'dir';

    /**
     * Get the path of the entry.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Get the type of the entry ("file" or "dir").
     *
     * @return string
     */
    public function type(): string;

    /**
     * Get the visibility of the entry.
     *
     * @return string|null
     */
    public function getVisibility(): ?string;

    /**
     * Get the last modified timestamp.
     *
     * @return int|null
     */
    public function lastModified(): ?int;

    /**
     * Create an instance from an array of attributes.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public static function fromArray(array $attributes): static;

    /**
     * Check if this entry is a file.
     *
     * @return bool
     */
    public function isFile(): bool;

    /**
     * Check if this entry is a directory.
     *
     * @return bool
     */
    public function isDir(): bool;

    /**
     * Create a copy with a different path.
     *
     * @param string $path New path.
     * @return static
     */
    public function withPath(string $path): static;

    /**
     * Get extra metadata associated with this entry.
     *
     * @return array<string, mixed>
     */
    public function extraMetadata(): array;
}