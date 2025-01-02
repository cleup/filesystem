<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use ArrayAccess;
use JsonSerializable;

interface FinderAttributesInterface extends JsonSerializable, ArrayAccess
{
    /**
     * @var string
     */
    public const ATTRIBUTE_PATH = 'path';

    /**
     * @var string
     */
    public const ATTRIBUTE_TYPE = 'type';

    /**
     * @var string
     */
    public const ATTRIBUTE_FILE_SIZE = 'file_size';

    /**
     * @var string
     */
    public const ATTRIBUTE_VISIBILITY = 'visibility';

    /**
     * @var string
     */
    public const ATTRIBUTE_LAST_MODIFIED = 'last_modified';

    /**
     * @var string
     */
    public const ATTRIBUTE_MIME_TYPE = 'mime_type';

    /**
     * @var string
     */
    public const ATTRIBUTE_EXTRA_METADATA = 'extra_metadata';

    /**
     * @var string
     */
    public const TYPE_FILE = 'file';

    /**
     * @var string
     */
    public const TYPE_DIRECTORY = 'dir';

    /**
     * @return string
     */
    public function path();

    /**
     * @return string
     */
    public function type();

    /**
     * @return string
     */
    public function getVisibility();

    /**
     * @return ?int
     */
    public function lastModified();

    /**
     * @param array $attributes
     * @return static
     */
    public static function fromArray($attributes);

    /**
     * @return bool
     */
    public function isFile();

    /**
     * @return bool
     */
    public function isDir();

    /**
     * @param string $path
     * @return static
     */
    public function withPath($path);

    /**
     * @return array
     */
    public function extraMetadata();
}
