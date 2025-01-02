<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Interfaces;

use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Exceptions\InvalidChecksumAlgoException;
use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;

interface DriverInterface extends AdapterInterface
{
    /**
     * Get configuration by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($key = null, $default = null);

    /**
     * Get the full path to the file that exists at the given relative path.
     *
     * @param string $path
     * @param bool $normalize
     * @return string
     */
    public function path($path, $normalize = false);

    /**
     * Normalize path
     * 
     * @param string $path
     * @return string
     */
    public function normalizePath($path);

    /**
     * Extract the file name from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function name($path, $pathPrefix = true);

    /**
     * Extract the trailing name component from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function basename($path, $pathPrefix = true);

    /**
     * Extract the parent directory from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function dirname($path, $pathPrefix = true);

    /**
     * Extract the file extension from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function extension($path, $pathPrefix = true);

    /**
     * Unique hashed name.
     *
     * @param string $path
     * @param bool $isExtension
     * @return string
     */
    public function uniqueHashedName($path, $isExtension = true);

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists($path);

    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param string $path
     * @param int  $flags
     * @return array|null
     * 
     * @throws ReadFileException
     * @throws FilesystemExceptionInterface
     */
    public function json($path, $flags = 0);

    /**
     * Get the checksum for a file.
     *
     * @param string $path
     * @param array $config
     * @return string|bool

     * @throws InvalidChecksumAlgoException
     */
    public function checksum($path, $config = []);

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array $config
     * @return bool
     * 
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function prepend($path, $data, $separator = PHP_EOL, $config = []);

    /**
     * Append to a file.
     * 
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array $config
     * @return bool
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function append($path, $data, $separator = PHP_EOL, $config = []);

    /**
     * Replace a given string within a given file.
     *
     * @param array|string  $search
     * @param array|string  $replace
     * @param string  $path
     * @param array $config
     * @return bool
     *
     * @throws WriteFileException
     * @throws FilesystemExceptionInterface
     */
    public function replaceInFile($search, $replace, $path, $config = []);

    /**
     * Get an array of all files in a directory.
     *
     * @param string|null  $directory
     * @param bool $recursive
     * @return array
     */
    public function files($directory = null, $recursive = false);

    /**
     * Get all of the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function directories($directory = null, $recursive = false);

    /**
     * Get the entire contents of a directory as a map.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function fileMap($directory = null, $recursive = false);
}
