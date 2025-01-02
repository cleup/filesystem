<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use function rtrim;
use function strlen;
use function substr;

final class PathPrefixer
{
    /**
     * @var string
     */
    private $prefix = '';

    /**
     * @param string $prefix
     * @param string $separator
     */
    public function __construct(
        $prefix,
        private $separator = '/'
    ) {
        $this->prefix = rtrim($prefix, '\\/');

        if ($this->prefix !== '' || $prefix === $separator) {
            $this->prefix .= $separator;
        }
    }

    /**
     * @param string $path
     * @return string
     */
    public function prefixPath($path)
    {
        return $this->prefix . ltrim($path, '\\/');
    }

    /**
     * @param string $path
     * @return string
     */
    public function stripPrefix($path)
    {
        return substr(
            $path,
            strlen($this->prefix)
        );
    }

    /**
     * @param string $path
     * @return string
     */
    public function stripDirectoryPrefix($path)
    {
        return rtrim($this->stripPrefix($path), '\\/');
    }

    /**
     * @param string $path
     * @return string
     */
    public function prefixDirectoryPath($path)
    {
        $prefixedPath = $this->prefixPath(rtrim($path, '\\/'));

        if ($prefixedPath === '' || substr($prefixedPath, -1) === $this->separator) {
            return $prefixedPath;
        }

        return $prefixedPath . $this->separator;
    }
}
