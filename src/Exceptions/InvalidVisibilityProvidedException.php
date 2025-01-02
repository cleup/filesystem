<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use InvalidArgumentException;

use function var_export;

class InvalidVisibilityProvidedException extends InvalidArgumentException implements FilesystemExceptionInterface
{
    /**
     * @param string $visibility
     * @param string $expectedMessage
     * @param static
     */
    public static function withVisibility($visibility, $expectedMessage)
    {
        $provided = var_export($visibility, true);
        $message = "Invalid visibility provided. Expected {$expectedMessage}, received {$provided}";

        throw new static($message);
    }
}
