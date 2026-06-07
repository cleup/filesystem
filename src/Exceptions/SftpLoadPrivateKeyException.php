<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when loading an SFTP private key fails.
 * Used by the file upload library for SFTP adapter authentication.
 */
class SftpLoadPrivateKeyException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @param string|null $message Error message.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     */
    public function __construct(
        ?string $message = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? 'Unable to load private key.',
            $code,
            $previous,
        );
    }
}