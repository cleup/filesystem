<?php

namespace Cleup\Filesystem\Support\MimeType;

use Cleup\Filesystem\Interfaces\ExtensionToMimeTypeMapInterface;

class OverridingExtensionToMimeTypeMap implements ExtensionToMimeTypeMapInterface
{
    /**
     * @var ExtensionToMimeTypeMapInterface
     */
    private $innerMap;

    /**
     * @var string[]
     */
    private $overrides;

    /**
     * @param array<string, string>  $overrides
     */
    public function __construct(ExtensionToMimeTypeMapInterface $innerMap, array $overrides)
    {
        $this->innerMap = $innerMap;
        $this->overrides = $overrides;
    }

    /**
     * @param string $extension
     * @return ?string
     */
    public function lookupMimeType($extension)
    {
        return $this->overrides[$extension] ?? $this->innerMap->lookupMimeType($extension);
    }
}
