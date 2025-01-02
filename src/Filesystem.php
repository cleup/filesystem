<?php

namespace Cleup\Filesystem;

use Cleup\Filesystem\Exceptions\DriverMethodException;
use Cleup\Filesystem\Interfaces\DriverInterface;
use Cleup\Filesystem\Driver;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\UnregisteredDriverException;

class Filesystem
{
    /**
     * The public visibility setting
     *
     * @var string
     */
    public const VISIBILITY_PUBLIC = 'public';

    /**
     * The private visibility setting
     *
     * @var string
     */
    public const VISIBILITY_PRIVATE = 'private';

    /**
     * @var string
     */
    public const OPTION_COPY_IDENTICAL_PATH = 'copyIdenticalPath';

    /**
     * @var string
     */
    public const OPTION_MOVE_IDENTICAL_PATH = 'moveIdenticalPath';

    /**
     * @var string
     */
    public const OPTION_VISIBILITY = 'visibility';

    /**
     * @var string
     */
    public const OPTION_DIRECTORY_VISIBILITY = 'directoryVisibility';

    /**
     * @var string
     */
    public const OPTION_RETAIN_VISIBILITY = 'retainVisibility';

    /**
     * @var string
     */
    public const OPTION_PERMISSIONS = 'permissions';


    /**
     * The name of the local disk
     * 
     * @var string
     */
    public const DISK_LOCAL = 'local';

    /**
     * The name of the ftp disk
     * 
     * @var string
     */
    public const DISK_FTP = 'ftp';

    /**
     * The name of the sftp disk
     * 
     * @var string
     */
    public const DISK_SFTP = "sftp";

    /**
     * The default adapter
     *
     * @var DriverInterface
     */
    private $driver;

    /**
     * The default config
     *
     * @var string
     */
    private $config;

    public function __construct(
        $config = [],
        protected $debug = false
    ) {
        $this->config = $config;
    }

    /**
     * Use the disk
     * 
     * @param string $name (static::DISK_*)
     * @param array $config
     * @return DriverInterface|null
     */
    public function manager($name, $config = [])
    {
        $args = [$config, $this->debug];
        $driverName = sprintf(
            "\\" . __NAMESPACE__ . "\Drivers\%sDriver",
            ucfirst($name)
        );

        if (class_exists($driverName)) {
            return new $driverName(...$args);
        } else {
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
    }

    public function __call($method, $arguments)
    {
        $className = "\\" . Driver::class;

        if (method_exists($className, $method)) {
            if (!$this->driver) {
                $this->driver = $this->manager(
                    static::DISK_LOCAL,
                    $this->config
                );
            }

            return $this->driver->{$method}(...$arguments);
        } else {
            if ($this->debug) {
                throw new DriverMethodException(
                    $className . '::' . $method . '()'
                );
            }
        }
    }
}
