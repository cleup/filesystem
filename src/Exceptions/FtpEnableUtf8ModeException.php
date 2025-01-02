<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FtpConnectionExceptionInterface;
use RuntimeException;

final class FtpEnableUtf8ModeException extends RuntimeException implements FtpConnectionExceptionInterface
{
}
