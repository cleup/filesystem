<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

interface FinderFileAttributesInterface extends FinderAttributesInterface
{
    /**
     * File size
     * 
     * @return int
     */
    public function size();

    /**
     * File mime type
     * 
     * @return string
     */
    public function mimeType();
}
