<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use RuntimeException;

class FtpInvalidListResponseReceivedException extends RuntimeException implements FilesystemExceptionInterface
{
}
