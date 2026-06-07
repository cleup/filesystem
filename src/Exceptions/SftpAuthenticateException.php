<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when SFTP authentication fails.
 * Used by the file upload library for SFTP adapter connections.
 */
class SftpAuthenticateException extends RuntimeException implements FilesystemExceptionInterface
{
    private ?string $connectionError = null;

    /**
     * @param string $message Error message.
     * @param int $code Error code.
     * @param Throwable|null $previous Previous exception.
     * @param string|null $lastError Last connection error message.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        ?string $lastError = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->connectionError = $lastError;
    }

    /**
     * Create an exception for failed password authentication.
     *
     * @param string|null $lastError Last connection error message.
     * @return static
     */
    public static function withPassword(?string $lastError = null): static
    {
        return new static(
            message: 'Unable to authenticate using a password.',
            lastError: $lastError,
        );
    }

    /**
     * Create an exception for failed private key authentication.
     *
     * @param string|null $lastError Last connection error message.
     * @return static
     */
    public static function withPrivateKey(?string $lastError = null): static
    {
        return new static(
            message: 'Unable to authenticate using a private key.',
            lastError: $lastError,
        );
    }

    /**
     * Create an exception for failed SSH agent authentication.
     *
     * @param string|null $lastError Last connection error message.
     * @return static
     */
    public static function withSshAgent(?string $lastError = null): static
    {
        return new static(
            message: 'Unable to authenticate using an SSH agent.',
            lastError: $lastError,
        );
    }

    /**
     * Get the last connection error message.
     *
     * @return string|null
     */
    public function connectionError(): ?string
    {
        return $this->connectionError;
    }
}