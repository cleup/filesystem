<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use Cleup\Filesystem\Interfaces\FinderFileAttributesInterface;

class FileAttributes extends FinderAttributes implements FinderFileAttributesInterface
{

    /**
     * @var string
     */
    private string $type = FinderFileAttributesInterface::TYPE_FILE;

    public function __construct(
        private string $path,
        private ?int $fileSize = null,
        private ?string $visibility = null,
        private ?int $lastModified = null,
        private ?string $mimeType = null,
        private array $extraMetadata = []
    ) {
        $this->path = ltrim($this->path, '/');
    }

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @return ?int
     */
    public function lastModified()
    {
        return $this->lastModified;
    }

    /**
     * @param array $attributes
     * @return static
     */
    public static function fromArray($attributes)
    {
        return new static(
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_PATH],
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_FILE_SIZE] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_VISIBILITY] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_LAST_MODIFIED] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_MIME_TYPE] ?? null,
            $attributes[FinderFileAttributesInterface::ATTRIBUTE_EXTRA_METADATA] ?? []
        );
    }

    /**
     * @return bool
     */
    public function isFile()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isDir()
    {
        return false;
    }

    /**
     * @param string $path
     * @return static
     */
    public function withPath($path)
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * @return array
     */
    public function extraMetadata()
    {
        return $this->extraMetadata;
    }

    /**
     * @return int
     */
    public function size()
    {
        return $this->fileSize ?? 0;
    }

    /**
     * @return ?string
     */
    public function mimeType()
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
