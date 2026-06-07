<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Finder;

use RuntimeException;

use function lcfirst;
use function str_replace;
use function ucwords;

/**
 * Base class for file/directory attributes with array access support.
 * Provides property access via array syntax for the file upload library.
 */
class FinderAttributes
{
    /**
     * Convert an offset name to camelCase property name.
     *
     * @param string $offset
     * @return string
     */
    private function formatPropertyName(string $offset): string
    {
        return lcfirst(str_replace('_', '', ucwords($offset, '_')));
    }

    /**
     * Check if an offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        $property = $this->formatPropertyName((string) $offset);

        return isset($this->{$property});
    }

    /**
     * Get the value at an offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        $property = $this->formatPropertyName((string) $offset);

        return $this->{$property};
    }

    /**
     * Set the value at an offset (not allowed — immutable).
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @throws RuntimeException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Properties can not be manipulated');
    }

    /**
     * Unset an offset (not allowed — immutable).
     *
     * @param mixed $offset
     * @return void
     * @throws RuntimeException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Properties can not be manipulated');
    }
}