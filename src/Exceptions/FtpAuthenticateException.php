<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;

final class FtpAuthenticateException extends RuntimeException implements FtpConnectionExceptionInterface
{
    public function __construct()
    {
        parent::__construct("Unable to login/authenticate with FTP");
    }
}
