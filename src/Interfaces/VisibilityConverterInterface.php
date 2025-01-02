<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface VisibilityConverterInterface
{
    /**
     * @param string $visibility
     * @return int
     */
    public function forFile($visibility);

    /**
     * @param string $visibility
     * @return int
     */
    public function forDirectory($visibility);

    /**
     * @param int $visibility
     * @return string
     */
    public function inverseForFile($visibility);

    /**
     * @param int $visibility
     * @return string
     */
    public function inverseForDirectory($visibility);

    /**
     * @return int
     */
    public function defaultForDirectories();
}
