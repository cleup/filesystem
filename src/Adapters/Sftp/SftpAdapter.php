<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Finder\DirectoryAttributes;
use Cleup\Filesystem\Finder\FileAttributes;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Cleup\Filesystem\Exceptions\CheckDirectoryExistenceException;
use Cleup\Filesystem\Exceptions\CheckFileExistenceException;
use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Support\VisibilityConverter;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;
use Cleup\Filesystem\Support\MimeType\FinfoMimeTypeDetector;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use Cleup\Filesystem\Interfaces\SftpConnectionProviderInterface;
use phpseclib3\Net\SFTP;
use Throwable;

use function rtrim;

class SftpAdapter implements AdapterInterface
{
    private VisibilityConverterInterface $visibilityConverter;
    private PathPrefixer $prefixer;
    private MimeTypeDetectorInterface $mimeTypeDetector;
    private bool $finderMimeTypeDetect = false;

    public function __construct(
        private SftpConnectionProviderInterface $connectionProvider,
        string $root,
        ?VisibilityConverterInterface $visibilityConverter = null,
        ?MimeTypeDetectorInterface $mimeTypeDetector = null,
        $finderMimeTypeDetect = false,
        private bool $detectMimeTypeUsingPath = false,
    ) {
        $this->prefixer = new PathPrefixer($root);
        $this->visibilityConverter = $visibilityConverter ?? new VisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->finderMimeTypeDetect = $finderMimeTypeDetect;
    }

    public function fileExists($path): bool
    {
        $location = $this->prefixer->prefixPath($path);

        try {
            return $this->connectionProvider->provideConnection()->is_file($location);
        } catch (Throwable $exception) {
            throw CheckFileExistenceException::forLocation($path, $exception);
        }
    }

    public function disconnect(): void
    {
        $this->connectionProvider->disconnect();
    }

    public function directoryExists($path): bool
    {
        $location = $this->prefixer->prefixDirectoryPath($path);

        try {
            return $this->connectionProvider->provideConnection()->is_dir($location);
        } catch (Throwable $exception) {
            throw CheckDirectoryExistenceException::forLocation($path, $exception);
        }
    }

    /**
     * @param string          $path
     * @param string|resource $contents
     * @param array           $config
     *
     * @throws FilesystemExceptionInterface
     */
    private function upload(string $path, $contents, $config = []): void
    {
        $this->ensureParentDirectoryExists($path, $config);
        $connection = $this->connectionProvider->provideConnection();
        $location = $this->prefixer->prefixPath($path);

        if (! $connection->put($location, $contents, SFTP::SOURCE_STRING)) {
            throw WriteFileException::atLocation($path, 'not able to write the file');
        }

        if ($visibility = ($config[Filesystem::OPTION_VISIBILITY] ?? null)) {
            $this->setVisibility($path, $visibility);
        }
    }

    private function ensureParentDirectoryExists(string $path, $config = []): void
    {
        $parentDirectory = dirname($path);

        if ($parentDirectory === '' || $parentDirectory === '.') {
            return;
        }

        $this->makeDirectory(
            $parentDirectory,
            $config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null
        );
    }

    private function makeDirectory(string $directory, ?string $visibility): void
    {
        $location = $this->prefixer->prefixPath($directory);
        $connection = $this->connectionProvider->provideConnection();

        if ($connection->is_dir($location)) {
            return;
        }

        $mode = $visibility ? $this->visibilityConverter->forDirectory(
            $visibility
        ) : $this->visibilityConverter->defaultForDirectories();

        if (! $connection->mkdir($location, $mode, true) && ! $connection->is_dir($location)) {
            throw CreateDirectoryException::atLocation($directory);
        }
    }

    public function put($path, $contents, $config = []): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (WriteFileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream($path, $contents, $config = []): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (WriteFileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function get($path): string
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $contents = $connection->get($location);

        if (! is_string($contents)) {
            throw ReadFileException::fromLocation($path);
        }

        return $contents;
    }

    public function readStream($path)
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        /** @var resource $readStream */
        $readStream = fopen('php://temp', 'w+');

        if (! $connection->get($location, $readStream)) {
            fclose($readStream);
            throw ReadFileException::fromLocation($path);
        }

        rewind($readStream);

        return $readStream;
    }

    public function delete($path): void
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $connection->delete($location);
    }

    public function deleteDirectory($path): void
    {
        $location = rtrim($this->prefixer->prefixPath($path), '/') . '/';
        $connection = $this->connectionProvider->provideConnection();
        $connection->delete($location);
        $connection->rmdir($location);
    }

    public function createDirectory($path, $config = []): void
    {
        $this->makeDirectory(
            $path,
            $config[Filesystem::OPTION_DIRECTORY_VISIBILITY]
                ?? $config[Filesystem::OPTION_VISIBILITY]
        );
    }

    public function setVisibility($path, $visibility): void
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $mode = $this->visibilityConverter->forFile($visibility);

        if (! $connection->chmod($mode, $location, false)) {
            throw SetVisibilityException::atLocation($path);
        }
    }

    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $stat = $connection->stat($location);

        if (! is_array($stat)) {
            throw RetrieveMetadataException::create($path, $type);
        }

        $attributes = $this->convertListingToAttributes($path, $stat);

        if (! $attributes instanceof FileAttributes) {
            throw RetrieveMetadataException::create($path, $type, 'path is not a file');
        }

        return $attributes;
    }

    public function mimeType($path): FileAttributes
    {
        try {
            $mimetype = $this->detectMimeTypeUsingPath
                ? $this->mimeTypeDetector->detectMimeTypeFromPath($path)
                : $this->mimeTypeDetector->detectMimeType($path, $this->get($path));
        } catch (Throwable $exception) {
            throw RetrieveMetadataException::mimeType($path, $exception->getMessage(), $exception);
        }

        if ($mimetype === null) {
            throw RetrieveMetadataException::mimeType($path, 'Unknown.');
        }

        return new FileAttributes($path, null, null, null, $mimetype);
    }

    public function lastModified($path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    public function size($path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    public function getVisibility($path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function finder($path, $deep): iterable
    {
        $connection = $this->connectionProvider->provideConnection();
        $location = $this->prefixer->prefixPath(rtrim($path, '/')) . '/';
        $listing = $connection->rawlist($location, false);

        if (false === $listing) {
            return;
        }

        foreach ($listing as $filename => $attributes) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            // Ensure numeric keys are strings.
            $filename = (string) $filename;
            $path = $this->prefixer->stripPrefix($location . ltrim($filename, '/'));
            $attributes = $this->convertListingToAttributes($path, $attributes);
            yield $attributes;

            if ($deep && $attributes->isDir()) {
                foreach ($this->finder($attributes->path(), true) as $child) {
                    yield $child;
                }
            }
        }
    }

    private function convertListingToAttributes(string $path, array $attributes): FinderAttributesInterface
    {
        $permissions = $attributes['mode'] & 0777;
        $lastModified = $attributes['mtime'] ?? null;

        if (!defined('NET_SFTP_TYPE_DIRECTORY'))
            define('NET_SFTP_TYPE_DIRECTORY', 2);

        if (($attributes['type'] ?? null) === NET_SFTP_TYPE_DIRECTORY) {
            return new DirectoryAttributes(
                ltrim($path, '/'),
                $this->visibilityConverter->inverseForDirectory($permissions),
                $lastModified
            );
        }

        return new FileAttributes(
            $path,
            $attributes['size'],
            $this->visibilityConverter->inverseForFile($permissions),
            $lastModified,
            $this->finderMimeTypeDetect
                ? $this->mimeTypeDetector->detectMimeTypeFromPath($path)
                : null
        );
    }

    public function move($from, $to, $config = []): void
    {
        $sourceLocation = $this->prefixer->prefixPath($from);
        $destinationLocation = $this->prefixer->prefixPath($to);
        $connection = $this->connectionProvider->provideConnection();

        try {
            $this->ensureParentDirectoryExists($to, $config);
        } catch (Throwable $exception) {
            throw MoveFileException::fromLocationTo($from, $to, $exception);
        }

        if ($sourceLocation === $destinationLocation) {
            return;
        }

        if ($connection->rename($sourceLocation, $destinationLocation)) {
            return;
        }

        // Overwrite existing file / dir
        if ($connection->is_file($destinationLocation)) {
            $this->delete($to);
            if ($connection->rename($sourceLocation, $destinationLocation)) {
                return;
            }
        }

        throw MoveFileException::fromLocationTo($from, $to);
    }

    public function copy($from, $to, $config = []): void
    {
        try {
            $readStream = $this->readStream($from);
            $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

            if (
                $visibility === null &&
                ($config[Filesystem::OPTION_RETAIN_VISIBILITY] ?? true) &&
                ($config['systemType'] ?? null) != 'windows'
            ) {
                $config[Filesystem::OPTION_VISIBILITY] = $this->getVisibility($from)->getVisibility();
            }

            $this->writeStream($to, $readStream, $config);
        } catch (Throwable $exception) {
            if (isset($readStream) && is_resource($readStream)) {
                @fclose($readStream);
            }

            throw CopyFileException::fromLocationTo($from, $to, $exception);
        }
    }
}
