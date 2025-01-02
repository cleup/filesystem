<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;
use function rtrim;

final class SetVisibilityException extends RuntimeException
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $reason;

    /**
     * @return string
     */
    public function reason()
    {
        return $this->reason;
    }

    /**
     * @param string $filename
     * @param string $extraMessage
     * @param ?Throwable $previous
     * @return static
     */
    public static function atLocation($filename, $extraMessage = '', $previous = null)
    {
        $message = "Unable to set visibility for file {$filename}. $extraMessage";
        $e = new static(rtrim($message), 0, $previous);
        $e->reason = $extraMessage;
        $e->path = $filename;

        return $e;
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "SET_VISIBILITY";
    }

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }
}
