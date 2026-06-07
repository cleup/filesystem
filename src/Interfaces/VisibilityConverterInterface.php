<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

/**
 * Converts between string and numeric visibility/permissions.
 */
interface VisibilityConverterInterface
{
    /**
     * Convert a visibility string to file permissions integer.
     *
     * @param string $visibility
     * @return int
     */
    public function forFile(string $visibility): int;

    /**
     * Convert a visibility string to directory permissions integer.
     *
     * @param string $visibility
     * @return int
     */
    public function forDirectory(string $visibility): int;

    /**
     * Convert file permissions integer to a visibility string.
     *
     * @param int $visibility
     * @return string
     */
    public function inverseForFile(int $visibility): string;

    /**
     * Convert directory permissions integer to a visibility string.
     *
     * @param int $visibility
     * @return string
     */
    public function inverseForDirectory(int $visibility): string;

    /**
     * Get the default permissions for directories.
     *
     * @return int
     */
    public function defaultForDirectories(): int;
}