<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Exceptions\CheckExistenceException;
use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\InvalidVisibilityProvidedException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Finder\FileAttributes;
use Generator;

/**
 * Unified adapter interface for file upload/download operations.
 * Implementations handle local, FTP, and SFTP filesystem interactions.
 */
interface AdapterInterface
{
    /**
     * Check if a file exists at the given path.
     *
     * @param string $path
     * @return bool
     *
     * @throws FilesystemExceptionInterface
     * @throws CheckExistenceException
     */
    public function fileExists(string $path): bool;

    /**
     * Check if a directory exists at the given path.
     *
     * @param string $path
     * @return bool
     *
     * @throws FilesystemExceptionInterface
     * @throws CheckExistenceException
     */
    public function directoryExists(string $path): bool;

    /**
     * Get the full contents of a file.
     *
     * @param string $path
     * @return string
     *
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function get(string $path): string;

    /**
     * Get a stream resource for reading a file.
     *
     * @param string $path
     * @return resource|mixed
     *
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function readStream(string $path): mixed;

    /**
     * Get the last modified timestamp of a file.
     *
     * @param string $path
     * @return FileAttributes
     *
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function lastModified(string $path): FileAttributes;

    /**
     * Get the file size in bytes.
     *
     * @param string $path
     * @return FileAttributes
     *
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function size(string $path): FileAttributes;

    /**
     * Get the MIME type of a file.
     *
     * @param string $path
     * @return FileAttributes
     *
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function mimeType(string $path): FileAttributes;

    /**
     * List contents of a directory, optionally recursively.
     *
     * @param string $path
     * @param bool $deep
     * @return Generator
     *
     * @throws FilesystemExceptionInterface
     */
    public function finder(string $path, bool $deep): Generator;

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return FileAttributes
     *
     * @throws RetrieveMetadataException
     * @throws FilesystemExceptionInterface
     */
    public function getVisibility(string $path): FileAttributes;

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return void
     *
     * @throws InvalidVisibilityProvidedException
     * @throws FilesystemExceptionInterface
     */
    public function setVisibility(string $path, string $visibility): void;

    /**
     * Write contents to a file.
     *
     * @param string $path
     * @param string|resource|mixed $contents
     * @param array $config
     * @return void
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function put(string $path, mixed $contents, array $config = []): void;

    /**
     * Write a stream to a file.
     *
     * @param string $path
     * @param resource|mixed $contents
     * @param array $config
     * @return void
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function writeStream(string $path, mixed $contents, array $config = []): void;

    /**
     * Create a directory and any missing parent directories.
     *
     * @param string $path
     * @param array $config
     * @return void
     *
     * @throws CreateDirectoryException
     * @throws FilesystemExceptionInterface
     */
    public function createDirectory(string $path, array $config = []): void;

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $path
     * @return void
     *
     * @throws DeleteDirectoryException
     * @throws FilesystemExceptionInterface
     */
    public function deleteDirectory(string $path): void;

    /**
     * Delete a file.
     *
     * @param string $path
     * @return void
     *
     * @throws DeleteFileException
     * @throws FilesystemExceptionInterface
     */
    public function delete(string $path): void;

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @param array $config
     * @return void
     *
     * @throws MoveFileException
     * @throws FilesystemExceptionInterface
     */
    public function move(string $from, string $to, array $config = []): void;

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @param array $config
     * @return void
     *
     * @throws CopyFileException
     * @throws FilesystemExceptionInterface
     */
    public function copy(string $from, string $to, array $config = []): void;
}