<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when the authenticity of an SFTP host cannot be established.
 * Used by the file upload library for SFTP adapter host fingerprint verification.
 */
class SftpEstablishAuthenticityOfHostException extends RuntimeException implements FilesystemExceptionInterface
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
     * Create an exception when the host authenticity cannot be verified.
     *
     * @param string $host Hostname or IP address.
     * @return static
     */
    public static function becauseTheAuthenticityCantBeEstablished(string $host): static
    {
        return new static("The authenticity of host $host can't be established.");
    }
}