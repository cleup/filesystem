<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Exceptions\InvalidChecksumAlgoException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Finder\Finder;

/**
 * Driver interface for file upload/download operations.
 * Provides path utilities, JSON handling, checksums, and bulk operations.
 * Independent from AdapterInterface — drivers compose adapters internally.
 */
interface DriverInterface
{
    /**
     * Get configuration by key, or the entire config array.
     *
     * @param string|null $key Configuration key.
     * @param mixed $default Default value if key not found.
     * @return mixed
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed;

    /**
     * Get the full path to a file.
     *
     * @param string $path Relative path.
     * @param bool $normalize Whether to normalize the path.
     * @return string
     */
    public function path(string $path, bool $normalize = false): string;

    /**
     * Extract the file name from a file path (without extension).
     *
     * @param string $path
     * @param bool $pathPrefix Whether to prefix the path.
     * @return string
     */
    public function name(string $path, bool $pathPrefix = true): string;

    /**
     * Extract the trailing name component from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix Whether to prefix the path.
     * @return string
     */
    public function basename(string $path, bool $pathPrefix = true): string;

    /**
     * Extract the parent directory from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix Whether to prefix the path.
     * @return string
     */
    public function dirname(string $path, bool $pathPrefix = true): string;

    /**
     * Extract the file extension from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix Whether to prefix the path.
     * @return string
     */
    public function extension(string $path, bool $pathPrefix = true): string;

    /**
     * Generate a unique hashed file name.
     *
     * @param string $path
     * @param bool $isExtension Whether to include the extension.
     * @return string
     */
    public function uniqueHashedName(string $path, bool $isExtension = true): string;

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool;

    /**
     * Determine if a directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function directoryExists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     *
     * @throws ReadFileException
     */
    public function get(string $path): string;

    /**
     * Get a resource to read the file.
     *
     * @param string $path
     * @return resource|mixed
     *
     * @throws ReadFileException
     */
    public function readStream(string $path): mixed;

    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param string $path
     * @param int $flags JSON decode flags.
     * @return array|null
     *
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function json(string $path, int $flags = 0): ?array;

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     *
     * @throws RetrieveMetadataException
     */
    public function lastModified(string $path): int;

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     *
     * @throws RetrieveMetadataException
     */
    public function size(string $path): int;

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false
     *
     * @throws RetrieveMetadataException
     */
    public function mimeType(string $path): string|false;

    /**
     * Get the checksum for a file.
     *
     * @param string $path
     * @param array<string, mixed> $config
     * @return string|false
     *
     * @throws InvalidChecksumAlgoException
     */
    public function checksum(string $path, array $config = []): string|false;

    /**
     * List directory contents with optional recursion.
     *
     * @param string $path
     * @param bool $deep
     * @return Finder
     *
     * @throws FilesystemExceptionInterface
     */
    public function finder(string $path, bool $deep = false): Finder;

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string
     *
     * @throws RetrieveMetadataException
     */
    public function getVisibility(string $path): string;

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     *
     * @throws SetVisibilityException
     */
    public function setVisibility(string $path, string $visibility): bool;

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param mixed $contents
     * @param array|string $config
     * @return bool
     *
     * @throws WriteFileException
     */
    public function put(string $path, mixed $contents, array|string $config = []): bool;

    /**
     * Write a stream to a file.
     *
     * @param string $path
     * @param resource|mixed $contents
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws WriteFileException
     */
    public function writeStream(string $path, mixed $contents, array $config = []): bool;

    /**
     * Prepend content to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL, array $config = []): bool;

    /**
     * Append content to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function append(string $path, string $data, string $separator = PHP_EOL, array $config = []): bool;

    /**
     * Replace content in a file.
     *
     * @param array|string $search
     * @param array|string $replace
     * @param string $path
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function replaceInFile(array|string $search, array|string $replace, string $path, array $config = []): bool;

    /**
     * Create a directory.
     *
     * @param string $path
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws CreateDirectoryException
     */
    public function createDirectory(string $path, array $config = []): bool;

    /**
     * Recursively delete a directory.
     *
     * @param string $path
     * @return bool
     *
     * @throws DeleteDirectoryException
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Delete one or more files.
     *
     * @param string|array $path
     * @return bool
     *
     * @throws DeleteFileException
     */
    public function delete(string|array $path): bool;

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws MoveFileException
     */
    public function move(string $from, string $to, array $config = []): bool;

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @param array<string, mixed> $config
     * @return bool
     *
     * @throws CopyFileException
     */
    public function copy(string $from, string $to, array $config = []): bool;

    /**
     * Get an array of all file paths in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array<int, string>
     */
    public function files(?string $directory = null, bool $recursive = false): array;

    /**
     * Get all directory paths within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array<int, string>
     */
    public function directories(?string $directory = null, bool $recursive = false): array;

    /**
     * Get a detailed map of directory contents.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array<int, array>
     */
    public function fileMap(?string $directory = null, bool $recursive = false): array;
}