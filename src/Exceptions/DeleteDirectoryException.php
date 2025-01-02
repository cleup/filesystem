<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use RuntimeException;
use Throwable;

final class DeleteDirectoryException extends RuntimeException
{
    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $reason;

    /**
     * @param string $path
     * @param string $reason 
     * @param ?Throwable $previous
     * @return static
     */
    public static function atLocation($path, $reason = '', $previous = null)
    {
        $e = new static(
            rtrim("Unable to delete directory located at: {$path}. {$reason}"),
            0,
            $previous
        );
        $e->path = $path;
        $e->reason = $reason;

        return $e;
    }

    /**
     * @return string
     */
    public function operation()
    {
        return "DELETE_DIRECTORY";
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
