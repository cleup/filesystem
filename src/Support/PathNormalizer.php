<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Cleup\Filesystem\Exceptions\CorruptedPathDetectedException;
use Cleup\Filesystem\Exceptions\PathTraversalDetectedException;
use Cleup\Filesystem\Interfaces\PathNormalizerInterface;

class PathNormalizer implements PathNormalizerInterface
{
    /**
     * @param string $path
     * @return string
     */
    public function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        $this->rejectFunkyWhiteSpace($path);

        return $this->normalizeRelativePath($path);
    }

    /**
     * @param string $path
     * @return void
     */
    private function rejectFunkyWhiteSpace($path)
    {
        if (preg_match('#\p{C}+#u', $path)) {
            throw CorruptedPathDetectedException::forPath($path);
        }
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizeRelativePath($path)
    {
        $parts = [];

        foreach (explode('/', $path) as $part) {
            switch ($part) {
                case '':
                case '.':
                    break;

                case '..':
                    if (empty($parts)) {
                        throw PathTraversalDetectedException::forPath($path);
                    }
                    array_pop($parts);
                    break;

                default:
                    $parts[] = $part;
                    break;
            }
        }

        return implode('/', $parts);
    }
}
