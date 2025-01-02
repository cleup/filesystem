<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

class SftpLoadPrivateKeyException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @param ?string $message
     * @param ?Throwable $previous
     * @return void
     */
    public function __construct($message = 'Unable to load private key.', $previous = null)
    {
        parent::__construct(
            $message ?? 'Unable to load private key.',
            0,
            $previous
        );
    }
}
