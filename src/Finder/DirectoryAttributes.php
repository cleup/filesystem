<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use Cleup\Filesystem\Interfaces\FinderAttributesInterface;

class DirectoryAttributes extends FinderAttributes implements FinderAttributesInterface
{

    /**
     * @var string
     */
    private $type = FinderAttributesInterface::TYPE_DIRECTORY;

    public function __construct(
        private string $path,
        private ?string $visibility = null,
        private ?int $lastModified = null,
        private array $extraMetadata = []
    ) {
        $this->path = trim($this->path, '/');
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
            $attributes[FinderAttributesInterface::ATTRIBUTE_PATH],
            $attributes[FinderAttributesInterface::ATTRIBUTE_VISIBILITY] ?? null,
            $attributes[FinderAttributesInterface::ATTRIBUTE_LAST_MODIFIED] ?? null,
            $attributes[FinderAttributesInterface::ATTRIBUTE_EXTRA_METADATA] ?? []
        );
    }

    /**
     * @return bool
     */
    public function isFile()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isDir()
    {
        return true;
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
