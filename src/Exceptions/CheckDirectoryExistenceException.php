<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

class CheckDirectoryExistenceException extends CheckExistenceException
{
    /**
     * @return string
     */
    public function operation()
    {
        return "DIRECTORY_EXISTS";
    }
}
