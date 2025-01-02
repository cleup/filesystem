<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;

class VisibilityConverter implements VisibilityConverterInterface
{
    public function __construct(
        private int $filePublic = 0644,
        private int $filePrivate = 0600,
        private int $directoryPublic = 0755,
        private int $directoryPrivate = 0700,
        private string $defaultForDirectories = Filesystem::VISIBILITY_PRIVATE
    ) {}

    /**
     * @param string $visibility
     * @return int
     */
    public function forFile($visibility)
    {
        VisibilityGuard::guardAgainstInvalidInput($visibility);

        return $visibility === Filesystem::VISIBILITY_PUBLIC
            ? $this->filePublic
            : $this->filePrivate;
    }

    /**
     * @param string $visibility
     * @return int
     */
    public function forDirectory($visibility)
    {
        VisibilityGuard::guardAgainstInvalidInput($visibility);

        return $visibility === Filesystem::VISIBILITY_PUBLIC
            ? $this->directoryPublic
            : $this->directoryPrivate;
    }

    /**
     * @param int $visibility
     * @return string
     */
    public function inverseForFile($visibility)
    {
        if ($visibility === $this->filePublic) {
            return Filesystem::VISIBILITY_PUBLIC;
        } elseif ($visibility === $this->filePrivate) {
            return Filesystem::VISIBILITY_PRIVATE;
        }

        return Filesystem::VISIBILITY_PUBLIC;
    }

    /**
     * @param int $visibility
     * @return string
     */
    public function inverseForDirectory($visibility)
    {
        if ($visibility === $this->directoryPublic) {
            return Filesystem::VISIBILITY_PUBLIC;
        } elseif ($visibility === $this->directoryPrivate) {
            return Filesystem::VISIBILITY_PRIVATE;
        }

        return Filesystem::VISIBILITY_PUBLIC;
    }

    /**
     * @return int
     */
    public function defaultForDirectories()
    {
        return $this->defaultForDirectories === Filesystem::VISIBILITY_PUBLIC
            ? $this->directoryPublic
            : $this->directoryPrivate;
    }

    /**
     * @param array $permissionMap
     * @param string $defaultForDirectories
     * @return static
     */
    public static function fromArray($permissionMap, $defaultForDirectories = Filesystem::VISIBILITY_PRIVATE)
    {
        return new VisibilityConverter(
            $permissionMap['file']['public'] ?? 0644,
            $permissionMap['file']['private'] ?? 0600,
            $permissionMap['dir']['public'] ?? 0755,
            $permissionMap['dir']['private'] ?? 0700,
            $defaultForDirectories
        );
    }
}
