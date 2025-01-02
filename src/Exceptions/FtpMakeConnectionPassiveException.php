<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;

class FtpMakeConnectionPassiveException extends RuntimeException implements FtpConnectionExceptionInterface
{
}
