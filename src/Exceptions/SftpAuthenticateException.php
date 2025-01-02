<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

class SftpAuthenticateException extends RuntimeException implements FilesystemExceptionInterface
{
    /**
     * @var ?string
     */
    private $connectionError;

    /**
     * @param string $message, 
     * @param ?string $lastError
     */
    public function __construct($message, $lastError = null)
    {
        parent::__construct($message);
        $this->connectionError = $lastError;
    }

    /**
     * @param ?string $lastError
     * @return static
     */
    public static function withPassword($lastError = null)
    {
        return new static('Unable to authenticate using a password.', $lastError);
    }

    /**
     * @param ?string $lastError
     * @return static
     */
    public static function withPrivateKey($lastError = null)
    {
        return new static('Unable to authenticate using a private key.', $lastError);
    }

    /**
     * @param ?string $lastError
     * @return static
     */
    public static function withSshAgent($lastError = null)
    {
        return new static('Unable to authenticate using an SSH agent.', $lastError);
    }

    /**
     * @return ?string
     */
    public function connectionError()
    {
        return $this->connectionError;
    }
}
