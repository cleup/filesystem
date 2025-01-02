<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

class CheckFileExistenceException extends CheckExistenceException
{
    /**
     * @return string
     */
    public function operation()
    {
        return "FILE_EXISTS";
    }
}
