<?php

declare(strict_types=1);

namespace Cleup\Filesystem;

use Cleup\Filesystem\Exceptions\DriverMethodException;
use Cleup\Filesystem\Exceptions\UnregisteredDriverException;
use Cleup\Filesystem\Interfaces\DriverInterface;

/**
 * Filesystem manager for the file upload library.
 * Manages drivers for local, FTP, and SFTP storage operations.
 */
class Filesystem
{
    /** @var string */
    public const VISIBILITY_PUBLIC = 'public';

    /** @var string */
    public const VISIBILITY_PRIVATE = 'private';

    /** @var string */
    public const OPTION_COPY_IDENTICAL_PATH = 'copyIdenticalPath';

    /** @var string */
    public const OPTION_MOVE_IDENTICAL_PATH = 'moveIdenticalPath';

    /** @var string */
    public const OPTION_VISIBILITY = 'visibility';

    /** @var string */
    public const OPTION_DIRECTORY_VISIBILITY = 'directoryVisibility';

    /** @var string */
    public const OPTION_RETAIN_VISIBILITY = 'retainVisibility';

    /** @var string */
    public const OPTION_PERMISSIONS = 'permissions';

    /** @var string */
    public const DISK_LOCAL = 'local';

    /** @var string */
    public const DISK_FTP = 'ftp';

    /** @var string */
    public const DISK_SFTP = 'sftp';

    private ?DriverInterface $driver = null;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Default configuration.
     * @param bool $debug Enable debug/exception mode.
     */
    public function __construct(
        array $config = [],
        protected bool $debug = false,
    ) {
        $this->config = $config;
    }

    /**
     * Create a driver instance by disk name.
     *
     * @param string $name Disk name (e.g., "local", "ftp", "sftp").
     * @param array<string, mixed> $config Additional configuration.
     * @return DriverInterface|null
     */
    public function manager(string $name, array $config = []): ?DriverInterface
    {
        $args = [$config, $this->debug];
        $driverName = sprintf(
            "\\" . __NAMESPACE__ . "\Drivers\%sDriver",
            ucfirst($name)
        );

        if (class_exists($driverName)) {
            return new $driverName(...$args);
        }

        if ($this->debug) {
            throw new UnregisteredDriverException(
                sprintf(
                    'The "%s" driver is not registered in the system and cannot be used.',
                    $driverName
                )
            );
        }

        return null;
    }

    /**
     * Proxy method calls to the default driver.
     *
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     * @throws DriverMethodException
     */
    public function __call(string $method, array $arguments): mixed
    {
        $className = Driver::class;

        if (method_exists($className, $method)) {
            if ($this->driver === null) {
                $this->driver = $this->manager(
                    static::DISK_LOCAL,
                    $this->config
                );
            }

            return $this->driver->{$method}(...$arguments);
        }

        if ($this->debug) {
            throw new DriverMethodException(
                $className . '::' . $method . '()'
            );
        }

        return null;
    }
}
