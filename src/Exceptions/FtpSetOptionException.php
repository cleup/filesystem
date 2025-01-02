<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;

class FtpSetOptionException extends RuntimeException implements FtpConnectionExceptionInterface
{
    /**
     * @param string $option
     * @return static
     */
    public static function whileSettingOption(string $option)
    {
        return new static("Unable to set FTP option $option.");
    }
}
