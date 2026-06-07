<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;

/**
 * Converts between string and numeric visibility/permissions.
 */
class VisibilityConverter implements VisibilityConverterInterface
{
    /**
     * @param int $filePublic Public file permissions (default: 0644).
     * @param int $filePrivate Private file permissions (default: 0600).
     * @param int $directoryPublic Public directory permissions (default: 0755).
     * @param int $directoryPrivate Private directory permissions (default: 0700).
     * @param string $defaultForDirectories Default visibility for directories.
     */
    public function __construct(
        private readonly int $filePublic = 0644,
        private readonly int $filePrivate = 0600,
        private readonly int $directoryPublic = 0755,
        private readonly int $directoryPrivate = 0700,
        private readonly string $defaultForDirectories = Filesystem::VISIBILITY_PRIVATE,
    ) {}

    /**
     * @inheritDoc
     */
    public function forFile(string $visibility): int
    {
        VisibilityGuard::guardAgainstInvalidInput($visibility);

        return $visibility === Filesystem::VISIBILITY_PUBLIC
            ? $this->filePublic
            : $this->filePrivate;
    }

    /**
     * @inheritDoc
     */
    public function forDirectory(string $visibility): int
    {
        VisibilityGuard::guardAgainstInvalidInput($visibility);

        return $visibility === Filesystem::VISIBILITY_PUBLIC
            ? $this->directoryPublic
            : $this->directoryPrivate;
    }

    /**
     * @inheritDoc
     */
    public function inverseForFile(int $visibility): string
    {
        if ($visibility === $this->filePublic) {
            return Filesystem::VISIBILITY_PUBLIC;
        }

        if ($visibility === $this->filePrivate) {
            return Filesystem::VISIBILITY_PRIVATE;
        }

        return Filesystem::VISIBILITY_PUBLIC;
    }

    /**
     * @inheritDoc
     */
    public function inverseForDirectory(int $visibility): string
    {
        if ($visibility === $this->directoryPublic) {
            return Filesystem::VISIBILITY_PUBLIC;
        }

        if ($visibility === $this->directoryPrivate) {
            return Filesystem::VISIBILITY_PRIVATE;
        }

        return Filesystem::VISIBILITY_PUBLIC;
    }

    /**
     * @inheritDoc
     */
    public function defaultForDirectories(): int
    {
        return $this->defaultForDirectories === Filesystem::VISIBILITY_PUBLIC
            ? $this->directoryPublic
            : $this->directoryPrivate;
    }

    /**
     * Create a VisibilityConverter from a configuration array.
     *
     * @param array<string, array<string, int>> $permissionMap
     * @param string $defaultForDirectories
     * @return static
     */
    public static function fromArray(array $permissionMap, string $defaultForDirectories = Filesystem::VISIBILITY_PRIVATE): static
    {
        return new static(
            filePublic: $permissionMap['file']['public'] ?? 0644,
            filePrivate: $permissionMap['file']['private'] ?? 0600,
            directoryPublic: $permissionMap['dir']['public'] ?? 0755,
            directoryPrivate: $permissionMap['dir']['private'] ?? 0700,
            defaultForDirectories: $defaultForDirectories,
        );
    }
}