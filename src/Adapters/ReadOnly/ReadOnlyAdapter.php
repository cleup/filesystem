<?php

namespace Cleup\Filesystem\Adapters\ReadOnly;

use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\WriteFileException;

class ReadOnlyAdapter implements AdapterInterface
{
    /**
     * @var string
     */
    private $message = 'This is a readonly adapter.';

    public function __construct(
        protected AdapterInterface $adapter
    ) {}

    /**
     * @param string $path
     * @return bool
     */
    public function fileExists($path)
    {
        return $this->adapter->fileExists($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function directoryExists($path)
    {
        return $this->adapter->directoryExists($path);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function get($path)
    {
        return $this->adapter->get($path);
    }

    /**
     * @param string $path
     * @return resource|null
     */
    public function readStream($path)
    {
        return $this->adapter->readStream($path);
    }

    /**
     * @param string $path
     * @return int
     */
    public function lastModified($path)
    {
        return $this->adapter->lastModified($path);
    }

    /**
     * @param string $path
     * @return int
     */
    public function size($path)
    {
        return $this->adapter->size($path);
    }

    /**
     * @param string $path
     * @return string|false
     */
    public function mimeType($path)
    {
        return $this->adapter->mimeType($path);
    }

    /**
     * @param string $path
     * @param bool $deep
     * @return iterable
     */
    public function finder($path, $deep)
    {
        return $this->adapter->finder($path, $deep);
    }

    /**
     * @param string $path
     * @return string|null
     */
    public function getVisibility($path)
    {
        return $this->adapter->getVisibility($path);
    }

    /**
     * @param string $path
     * @param string $visibility
     * @return void
     */
    public function setVisibility($path, $visibility)
    {
        throw SetVisibilityException::atLocation($path, $this->message);
    }

    /**
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|string|resource $contents
     * @param mixed $config
     * @return void
     */
    public function put($path, $contents,  $config = [])
    {
        throw WriteFileException::atLocation($path, $this->message);
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param array $options
     * @return void
     */
    public function writeStream($path, $contents,  $config = [])
    {
        throw WriteFileException::atLocation($path, $this->message);
    }

    /**
     * @param string $path
     * @return void
     */
    public function createDirectory($path,  $config = [])
    {
        throw CreateDirectoryException::atLocation($path, $this->message);
    }

    /**
     * @param string $directory
     * @return void
     */
    public function deleteDirectory($path)
    {
        throw DeleteDirectoryException::atLocation($path, $this->message);
    }

    /**
     * @param string $path
     * @return void
     */
    public function delete($path)
    {
        throw DeleteFileException::atLocation($path, $this->message);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $config
     * @return void
     */
    public function move($from, $to, $config = [])
    {
        throw new MoveFileException("Unable to move file from $from to $to as this is a readonly adapter.");
    }

    /**
     * @param string $from
     * @param string $to
     * @param array $config
     * @return void
     */
    public function copy($from, $to,  $config = [])
    {
        throw new CopyFileException("Unable to copy file from $from to $to as this is a readonly adapter.");
    }
}
