<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

final class FinderException extends RuntimeException
{
    /**
     * @param string $path
     * @param bool $deep
     * @param Throwable $previous
     */
    public static function atLocation($path, $deep, $previous)
    {
        $message = "Unable to list contents for '$path', " . ($deep ? 'deep' : 'shallow') . " listing\n\n"
            . 'Reason: ' . $previous->getMessage();

        return new static($message, 0, $previous);
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "FINDER";
    }
}
