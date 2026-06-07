<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Finder\DirectoryAttributes;
use Cleup\Filesystem\Finder\FileAttributes;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use Cleup\Filesystem\Interfaces\SftpConnectionProviderInterface;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;
use Cleup\Filesystem\Support\MimeType\FinfoMimeTypeDetector;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Support\VisibilityConverter;
use Generator;
use phpseclib3\Net\SFTP;
use Throwable;

use function rtrim;

/**
 * SFTP adapter for file upload/download operations.
 * Provides SFTP filesystem interaction through the unified AdapterInterface.
 *
 * @inheritDoc
 */
class SftpAdapter implements AdapterInterface
{
    private VisibilityConverterInterface $visibilityConverter;
    private PathPrefixer $prefixer;
    private MimeTypeDetectorInterface $mimeTypeDetector;
    private bool $finderMimeTypeDetect = false;

    /**
     * @param SftpConnectionProviderInterface $connectionProvider SFTP connection provider.
     * @param string $root Root directory path.
     * @param VisibilityConverterInterface|null $visibilityConverter Converts between string and numeric permissions.
     * @param MimeTypeDetectorInterface|null $mimeTypeDetector Detects MIME types for files.
     * @param bool $finderMimeTypeDetect Whether to detect MIME types during directory listing.
     * @param bool $detectMimeTypeUsingPath Whether to detect MIME types by path instead of content.
     */
    public function __construct(
        private readonly SftpConnectionProviderInterface $connectionProvider,
        string $root,
        ?VisibilityConverterInterface $visibilityConverter = null,
        ?MimeTypeDetectorInterface $mimeTypeDetector = null,
        bool $finderMimeTypeDetect = false,
        private readonly bool $detectMimeTypeUsingPath = false,
    ) {
        $this->prefixer = new PathPrefixer($root);
        $this->visibilityConverter = $visibilityConverter ?? new VisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->finderMimeTypeDetect = $finderMimeTypeDetect;
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        $location = $this->prefixer->prefixPath($path);

        return $this->connectionProvider->provideConnection()->is_file($location);
    }

    /**
     * Disconnect the SFTP connection.
     */
    public function disconnect(): void
    {
        $this->connectionProvider->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        $location = $this->prefixer->prefixDirectoryPath($path);

        return $this->connectionProvider->provideConnection()->is_dir($location);
    }

    /**
     * Upload contents to a file.
     *
     * @param string $path
     * @param string|resource $contents
     * @param array<string, mixed> $config
     * @throws WriteFileException
     */
    private function upload(string $path, mixed $contents, array $config = []): void
    {
        $this->ensureParentDirectoryExists($path, $config);
        $connection = $this->connectionProvider->provideConnection();
        $location = $this->prefixer->prefixPath($path);

        if (! $connection->put($location, $contents, SFTP::SOURCE_STRING)) {
            throw WriteFileException::atLocation($path, 'not able to write the file');
        }

        $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

        if ($visibility !== null) {
            $this->setVisibility($path, $visibility);
        }
    }

    /**
     * Ensure the parent directory of a file path exists.
     *
     * @param string $path
     * @param array<string, mixed> $config
     * @throws CreateDirectoryException
     */
    private function ensureParentDirectoryExists(string $path, array $config = []): void
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

    /**
     * Create a directory if it doesn't exist.
     *
     * @param string $directory
     * @param string|null $visibility
     * @throws CreateDirectoryException
     */
    private function makeDirectory(string $directory, ?string $visibility): void
    {
        $location = $this->prefixer->prefixPath($directory);
        $connection = $this->connectionProvider->provideConnection();

        if ($connection->is_dir($location)) {
            return;
        }

        $mode = $visibility !== null
            ? $this->visibilityConverter->forDirectory($visibility)
            : $this->visibilityConverter->defaultForDirectories();

        if (! $connection->mkdir($location, $mode, true) && ! $connection->is_dir($location)) {
            throw CreateDirectoryException::atLocation($directory);
        }
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, mixed $contents, array $config = []): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (WriteFileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, mixed $contents, array $config = []): void
    {
        try {
            $this->upload($path, $contents, $config);
        } catch (WriteFileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $contents = $connection->get($location);

        if (! is_string($contents)) {
            throw ReadFileException::fromLocation($path);
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path): mixed
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

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $connection->delete($location);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        $location = rtrim($this->prefixer->prefixPath($path), '/') . '/';
        $connection = $this->connectionProvider->provideConnection();
        $connection->delete($location);
        $connection->rmdir($location);
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        $this->makeDirectory(
            $path,
            $config[Filesystem::OPTION_DIRECTORY_VISIBILITY]
                ?? $config[Filesystem::OPTION_VISIBILITY]
                ?? null
        );
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $location = $this->prefixer->prefixPath($path);
        $connection = $this->connectionProvider->provideConnection();
        $mode = $this->visibilityConverter->forFile($visibility);

        if (! $connection->chmod($mode, $location, false)) {
            throw SetVisibilityException::atLocation($path);
        }
    }

    /**
     * Fetch file metadata using SFTP stat.
     *
     * @param string $path
     * @param string $type Metadata type being requested.
     * @return FileAttributes
     * @throws RetrieveMetadataException
     */
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

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
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

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    /**
     * @inheritDoc
     */
    public function finder(string $path, bool $deep): Generator
    {
        $connection = $this->connectionProvider->provideConnection();
        $location = $this->prefixer->prefixPath(rtrim($path, '/')) . '/';
        $listing = $connection->rawlist($location, false);

        if ($listing === false) {
            return;
        }

        foreach ($listing as $filename => $attributes) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $filename = (string) $filename;
            $path = $this->prefixer->stripPrefix($location . ltrim($filename, '/'));
            $attributes = $this->convertListingToAttributes($path, $attributes);
            yield $attributes;

            if ($deep && $attributes->isDir()) {
                yield from $this->finder($attributes->path(), true);
            }
        }
    }

    /**
     * Convert raw SFTP listing attributes to a FinderAttributesInterface object.
     *
     * @param string $path
     * @param array<string, mixed> $attributes
     * @return FinderAttributesInterface
     */
    private function convertListingToAttributes(string $path, array $attributes): FinderAttributesInterface
    {
        $permissions = $attributes['mode'] & 0777;
        $lastModified = $attributes['mtime'] ?? null;

        if (! defined('NET_SFTP_TYPE_DIRECTORY')) {
            define('NET_SFTP_TYPE_DIRECTORY', 2);
        }

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

    /**
     * @inheritDoc
     */
    public function move(string $from, string $to, array $config = []): void
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

        if ($connection->is_file($destinationLocation)) {
            $this->delete($to);
            if ($connection->rename($sourceLocation, $destinationLocation)) {
                return;
            }
        }

        throw MoveFileException::fromLocationTo($from, $to);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $from, string $to, array $config = []): void
    {
        try {
            $readStream = $this->readStream($from);
            $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

            if (
                $visibility === null &&
                ($config[Filesystem::OPTION_RETAIN_VISIBILITY] ?? true) &&
                ($config['systemType'] ?? null) !== 'windows'
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