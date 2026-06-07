<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Exception;

/**
 * Normalizes file paths.
 */
class Path
{
    /**
     * Normalize a file path by removing control characters, resolving relative paths,
     * and preventing path traversal.
     *
     * @param string $path
     * @return string
     * @throws Exception
     */
    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (preg_match('#\p{C}+#u', $path)) {
            throw new Exception("Corrupted path detected: " . $path);
        }

        $parts = [];

        foreach (explode('/', $path) as $part) {
            switch ($part) {
                case '':
                case '.':
                    break;

                case '..':
                    if (empty($parts)) {
                        throw new Exception('Path traversal detected: ' . $path);
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
