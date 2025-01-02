<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Finder\FileAttributes;
use RuntimeException;
use Throwable;

final class RetrieveMetadataException extends RuntimeException
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $metadataType;

    /**
     * @var string
     */
    private $reason;

    /**
     * @param string $path
     * @param string $reason
     * @param ?Throwable $previous
     * @return self
     */
    public static function lastModified($path,  $reason = '', $previous = null)
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_LAST_MODIFIED,
            $reason,
            $previous
        );
    }

    /**
     * @param string $path
     * @param string $reason
     * @param ?Throwable $previous
     * @return self
     */
    public static function getVisibility($path, $reason = '',  $previous = null)
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_VISIBILITY,
            $reason,
            $previous
        );
    }

    /**
     * @param string $path
     * @param string $reason
     * @param ?Throwable $previous
     * @return self
     */
    public static function size($path, $reason = '', $previous = null)
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_FILE_SIZE,
            $reason,
            $previous
        );
    }

    /**
     * @param string $path
     * @param string $reason
     * @param ?Throwable $previous
     * @return self
     */
    public static function mimeType($path, $reason = '', $previous = null)
    {
        return static::create(
            $path,
            FileAttributes::ATTRIBUTE_MIME_TYPE,
            $reason,
            $previous
        );
    }

    /**
     * @param string $path
     * @param string $type
     * @param string $reason
     * @param ?Throwable $previous
     * @return self
     */
    public static function create($path, $type, $reason = '', $previous = null)
    {
        $e = new static("Unable to retrieve the $type for file at path: $path. {$reason}", 0, $previous);
        $e->reason = $reason;
        $e->path = $path;
        $e->metadataType = $type;

        return $e;
    }

    /**
     * @return string
     */
    public function reason()
    {
        return $this->reason;
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
    public function metadataType()
    {
        return $this->metadataType;
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "RETRIEVE_METADATA";
    }
}
