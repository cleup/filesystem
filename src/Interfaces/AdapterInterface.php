<?php

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CheckExistenceException;
use Cleup\Filesystem\Exceptions\InvalidVisibilityProvidedException;
use Cleup\Filesystem\Finder\FileAttributes;

interface AdapterInterface
{
    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     * 
     * @throws FilesystemExceptionInterface
     * @throws CheckExistenceException
     */
    public function fileExists($path);

    /**
     * Determine if a directory exists.
     *
     * @param string $path
     * @return bool
     * 
     * @throws FilesystemExceptionInterface
     * @throws CheckExistenceException
     */
    public function directoryExists($path);

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string|null
     * 
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function get($path);

    /**
     * Get a resource to read the file.
     *
     * @param string $path
     * @return resource|null The path resource or null on failure.
     *
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function readStream($path);

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int|FileAttributes
     * 
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function lastModified($path);

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int|FileAttributes
     * 
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function size($path);

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false|FileAttributes
     * 
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function mimeType($path);

    /**
     * Finder
     * 
     * @param string $path
     * @param bool $deep
     * @return iterable<FinderAttributesInterface>
     *
     * @throws FilesystemExceptionInterface
     */
    public function finder($path, $deep);

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string|null|FileAttributes
     * 
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function getVisibility($path);

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool|void
     * 
     * @throws InvalidVisibilityProvidedException
     * @throws FilesystemExceptionInterface
     */
    public function setVisibility($path, $visibility);

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|string|resource $contents
     * @param mixed $config
     * @return bool|void
     * 
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function put($path, $contents, $config = []);

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param array $options
     * @return bool|void
     * 
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function writeStream($path, $contents, $config = []);

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool|void
     * 
     * @throws CreateDirectoryException
     * @throws FilesystemExceptionInterface
     */
    public function createDirectory($path, $config = []);

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool|void
     * 
     * @throws DeleteDirectoryException
     * @throws FilesystemExceptionInterface
     */
    public function deleteDirectory($path);

    /**
     * Delete the file at a given path.
     *
     * @param string|array $path
     * @return bool|void
     * 
     * @throws DeleteFileException
     * @throws FilesystemExceptionInterface
     */
    public function delete($path);



    /**
     * Move a file to a new path.
     *
     * @param string $from
     * @param string $to
     * @param array $config
     * @return bool|void
     * 
     * @throws MoveFileException
     * @throws FilesystemExceptionInterface
     */
    public function move($from, $to, $config = []);

    /**
     * Copy a file to a new path.
     *
     * @param string $from
     * @param string $to
     * @param array $config
     * @return bool|void
     * 
     * @throws CopyFileException
     * @throws FilesystemExceptionInterface
     */
    public function copy($from, $to, $config = []);
}
