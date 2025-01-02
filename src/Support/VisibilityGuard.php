<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Support;

use Cleup\Filesystem\Exceptions\InvalidVisibilityProvidedException;
use Cleup\Filesystem\Filesystem;

final class VisibilityGuard
{
    /**
     * @param string $visibility
     * @return void
     */
    public static function guardAgainstInvalidInput($visibility)
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
