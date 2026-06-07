<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use InvalidArgumentException;
use Throwable;

use function var_export;

/**
 * Exception thrown when an invalid visibility value is provided.
 * Used by the file upload library for permission setting in local, FTP, and SFTP adapters.
 */
class InvalidVisibilityProvidedException extends InvalidArgumentException implements FilesystemExceptionInterface
{
    /**
     * @param string $message Error message.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for an invalid visibility value.
     *
     * @param string $visibility The invalid visibility provided.
     * @param string $expectedMessage Description of what was expected.
     * @return static
     */
    public static function withVisibility(string $visibility, string $expectedMessage): static
    {
        $provided = var_export($visibility, true);
        $message = "Invalid visibility provided. Expected {$expectedMessage}, received {$provided}";

        throw new static($message);
    }
}