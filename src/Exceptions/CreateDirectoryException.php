<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

final class CreateDirectoryException extends RuntimeException
{
    /**
     * @var string
     */
    private string $path;

    /**
     * @var string
     */
    private string $reason = '';

    /**
     * @param string $dirname
     * @param string $errorMessage
     * @param ?Throwable $previous
     * @return static
     */
    public static function atLocation($dirname, $errorMessage = '', $previous = null)
    {
        $message = "Unable to create a directory at {$dirname}. {$errorMessage}";
        $e = new static(rtrim($message), 0, $previous);
        $e->path = $dirname;
        $e->reason = $errorMessage;

        return $e;
    }

    /**
     * @param string $dirname
     * @param Throwable $previous
     * @return static
     */
    public static function dueToFailure($dirname, $previous)
    {
        $reason = $previous instanceof self ? $previous->reason() : '';
        $message = "Unable to create a directory at $dirname. $reason";
        $e = new static(
            rtrim($message),
            0,
            $previous
        );
        $e->path = $dirname;
        $e->reason = $reason ?: $message;

        return $e;
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "CREATE_DIRECTORY";
    }

    /**
     * @return string
     */
    public function reason()
    {
        return $this->reason;
    }


    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }
}
