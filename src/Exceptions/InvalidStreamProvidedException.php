<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Exceptions;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use InvalidArgumentException;

class InvalidStreamProvidedException extends InvalidArgumentException implements FilesystemExceptionInterface
{
}
