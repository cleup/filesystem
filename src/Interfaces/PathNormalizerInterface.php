<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface PathNormalizerInterface
{
    /**
     * @param string $path
     * @return string
     */
    public function normalizePath($path);
}
