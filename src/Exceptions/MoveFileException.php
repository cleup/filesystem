<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

final class MoveFileException extends RuntimeException
{
    /**
     * @var string
     */
    private $from;

    /**
     * @var string
     */
    private $to;

    /**
     * @param string $from
     * @param string $to
     * @return static
     */
    public static function fromAndToAreTheSame($from, $to)
    {
        return static::because('Source and destination are the same', $from, $to);
    }

    /**
     * @return string
     */
    public function from()
    {
        return $this->from;
    }

    /**
     * @return string
     */
    public function to(): string
    {
        return $this->to;
    }

    /**
     * @param string $fromPath
     * @param string $toPath
     * @param ?Throwable $previous
     * @return static
     */
    public static function fromLocationTo($fromPath, $toPath, $previous = null)
    {
        $message = $previous?->getMessage() ?? "Unable to move file from $fromPath to $toPath";
        $e = new static($message, 0, $previous);
        $e->from = $fromPath;
        $e->to = $toPath;

        return $e;
    }

    /**
     * @param string $reason
     * @param string $fromPath
     * @param string $toPath
     * @return static
     */
    public static function because($reason, $fromPath, $toPath)
    {
        $message = "Unable to move file from $fromPath to $toPath, because $reason";
        $e = new static($message);
        $e->from = $fromPath;
        $e->to = $toPath;

        return $e;
    }
    /**
     * @return string
     */
    public function operation()
    {
        return "MOVE";
    }
}
