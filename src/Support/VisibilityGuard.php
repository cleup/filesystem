<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Cleup\Filesystem\Exceptions\InvalidVisibilityProvidedException;
use Cleup\Filesystem\Filesystem;

/**
 * Guards against invalid visibility values.
 */
final class VisibilityGuard
{
    /**
     * Validate that the given visibility is either "public" or "private".
     *
     * @param string $visibility
     * @return void
     * @throws InvalidVisibilityProvidedException
     */
    public static function guardAgainstInvalidInput(string $visibility): void
    {
        if (
            $visibility !== Filesystem::VISIBILITY_PUBLIC &&
            $visibility !== Filesystem::VISIBILITY_PRIVATE
        ) {
            $className = Filesystem::class;

            throw InvalidVisibilityProvidedException::withVisibility(
                $visibility,
                "either {$className}::VISIBILITY_PUBLIC or {$className}::VISIBILITY_PRIVATE"
            );
        }
    }
}