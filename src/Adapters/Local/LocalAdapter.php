<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Local;

use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\SymbolicLinkEncounteredException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Finder\DirectoryAttributes;
use Cleup\Filesystem\Finder\FileAttributes;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;
use Cleup\Filesystem\Support\MimeType\FinfoMimeTypeDetector;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Support\VisibilityConverter;
use DirectoryIterator;
use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;
use function chmod;
use function clearstatcache;
use function dirname;
use function error_clear_last;
use function error_get_last;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;

/**
 * Local filesystem adapter for file upload/download operations.
 * Provides local file storage interaction through the unified AdapterInterface.
 *
 * @inheritDoc
 */
class LocalAdapter implements AdapterInterface
{
    /** @var int */
    public const SKIP_LINKS = 0001;

    /** @var int */
    public const DISALLOW_LINKS = 0002;

    private PathPrefixer $prefixer;
    private VisibilityConverterInterface $visibility;
    private MimeTypeDetectorInterface $mimeTypeDetector;
    private string $rootLocation;
    private bool $disableRootLocation = false;
    private bool $finderMimeTypeDetect = false;

    /**
     * @param string|null $location Root directory path, or null for no root.
     * @param VisibilityConverterInterface|null $visibility Visibility converter.
     * @param int $writeFlags File write flags (e.g., LOCK_EX).
     * @param int $linkHandling How to handle symbolic links.
     * @param MimeTypeDetectorInterface|null $mimeTypeDetector MIME type detector.
     * @param bool $finderMimeTypeDetect Whether to detect MIME types during directory listing.
     */
    public function __construct(
        ?string $location = null,
        ?VisibilityConverterInterface $visibility = null,
        private int $writeFlags = LOCK_EX,
        private int $linkHandling = self::DISALLOW_LINKS,
        ?MimeTypeDetectorInterface $mimeTypeDetector = null,
        bool $finderMimeTypeDetect = false,
    ) {
        $this->prefixer = new PathPrefixer($location ?? '', DIRECTORY_SEPARATOR);
        $this->visibility = $visibility ?? new VisibilityConverter();
        $this->rootLocation = $location ?? '';
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();

        if ($location === null || $location === '') {
            $this->disableRootLocation = true;
        }

        $this->finderMimeTypeDetect = $finderMimeTypeDetect;
    }

    /**
     * Ensure the root directory exists.
     */
    private function ensureRootDirectoryExists(): void
    {
        if ($this->disableRootLocation) {
            return;
        }

        $this->ensureDirectoryExists($this->rootLocation, $this->visibility->defaultForDirectories());
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, mixed $contents, array $config = []): void
    {
        $this->upload($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, mixed $contents, array $config = []): void
    {
        $this->upload($path, $contents, $config);
    }

    /**
     * Upload contents to a file.
     *
     * @param string $path
     * @param resource|string $contents
     * @param array<string, mixed> $config
     * @throws WriteFileException
     */
    private function upload(string $path, mixed $contents, array $config = []): void
    {
        $prefixedLocation = $this->prefixer->prefixPath($path);
        $this->ensureRootDirectoryExists();
        $this->ensureDirectoryExists(
            dirname($prefixedLocation),
            $this->resolveDirectoryVisibility(
                $config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null
            )
        );
        error_clear_last();

        if (@file_put_contents($prefixedLocation, $contents, $this->writeFlags) === false) {
            throw WriteFileException::atLocation($path, error_get_last()['message'] ?? '');
        }

        $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

        if ($visibility !== null) {
            $this->setVisibility($path, (string) $visibility);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        if (! file_exists($location)) {
            return;
        }

        error_clear_last();

        if (! @unlink($location)) {
            throw DeleteFileException::atLocation($location, error_get_last()['message'] ?? '');
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        $location = $this->prefixer->prefixPath($path);

        if (! is_dir($location)) {
            return;
        }

        $contents = $this->listDirectoryRecursively($location, RecursiveIteratorIterator::CHILD_FIRST);

        /** @var SplFileInfo $file */
        foreach ($contents as $file) {
            if (! $this->deleteFileInfoObject($file)) {
                throw DeleteDirectoryException::atLocation($path, "Unable to delete file at " . $file->getPathname());
            }
        }

        unset($contents);

        if (! @rmdir($location)) {
            throw DeleteDirectoryException::atLocation($path, error_get_last()['message'] ?? '');
        }
    }

    /**
     * Recursively list directory contents.
     *
     * @param string $path
     * @param int $mode Iterator mode.
     * @return Generator<SplFileInfo>
     */
    private function listDirectoryRecursively(
        string $path,
        int $mode = RecursiveIteratorIterator::SELF_FIRST
    ): Generator {
        if (! is_dir($path)) {
            return;
        }

        yield from new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            $mode
        );
    }

    /**
     * Delete a file, directory, or symlink.
     *
     * @param SplFileInfo $file
     * @return bool
     */
    protected function deleteFileInfoObject(SplFileInfo $file): bool
    {
        switch ($file->getType()) {
            case 'dir':
                return @rmdir((string) $file->getRealPath());
            case 'link':
                return @unlink((string) $file->getPathname());
            default:
                return @unlink((string) $file->getRealPath());
        }
    }

    /**
     * @inheritDoc
     */
    public function finder(string $path, bool $deep): Generator
    {
        $location = $this->prefixer->prefixPath($path);

        if (! is_dir($location)) {
            return;
        }

        /** @var SplFileInfo[] $iterator */
        $iterator = $deep ? $this->listDirectoryRecursively($location) : $this->listDirectory($location);

        foreach ($iterator as $fileInfo) {
            $pathName = $fileInfo->getPathname();

            try {
                if ($fileInfo->isLink()) {
                    if ($this->linkHandling & self::SKIP_LINKS) {
                        continue;
                    }
                    throw SymbolicLinkEncounteredException::atLocation($pathName);
                }

                $path = $this->prefixer->stripPrefix($pathName);
                $lastModified = $fileInfo->getMTime();
                $isDirectory = $fileInfo->isDir();

                $permissions = octdec(substr(sprintf('%o', $fileInfo->getPerms()), -4));
                $visibility = $isDirectory
                    ? $this->visibility->inverseForDirectory($permissions)
                    : $this->visibility->inverseForFile($permissions);

                yield $isDirectory
                    ? new DirectoryAttributes(
                        str_replace('\\', '/', $path),
                        $visibility,
                        $lastModified
                    )
                    : new FileAttributes(
                        str_replace('\\', '/', $path),
                        $fileInfo->getSize(),
                        $visibility,
                        $lastModified,
                        $this->finderMimeTypeDetect
                            ? $this->mimeTypeDetector->detectMimeTypeFromFile(str_replace('\\', '/', $path))
                            : null
                    );
            } catch (Throwable $exception) {
                if (file_exists($pathName)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function move(string $from, string $to, array $config = []): void
    {
        $sourcePath = $this->prefixer->prefixPath($from);
        $destinationPath = $this->prefixer->prefixPath($to);

        $this->ensureRootDirectoryExists();
        $this->ensureDirectoryExists(
            dirname($destinationPath),
            $this->resolveDirectoryVisibility(
                $config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null
            )
        );

        if (! @rename($sourcePath, $destinationPath)) {
            throw MoveFileException::because(error_get_last()['message'] ?? 'unknown reason', $from, $to);
        }

        $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

        if ($visibility !== null) {
            $this->setVisibility($to, (string) $visibility);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $from, string $to, array $config = []): void
    {
        $sourcePath = $this->prefixer->prefixPath($from);
        $destinationPath = $this->prefixer->prefixPath($to);
        $this->ensureRootDirectoryExists();
        $this->ensureDirectoryExists(
            dirname($destinationPath),
            $this->resolveDirectoryVisibility($config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null)
        );

        if ($sourcePath !== $destinationPath && ! @copy($sourcePath, $destinationPath)) {
            throw CopyFileException::because(error_get_last()['message'] ?? 'unknown', $from, $to);
        }

        $defaultVisibility = ($config[Filesystem::OPTION_RETAIN_VISIBILITY] ?? true)
            ? $this->getVisibility($from)->getVisibility()
            : null;

        $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? $defaultVisibility;

        if ($visibility !== null) {
            $this->setVisibility($to, (string) $visibility);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        $location = $this->prefixer->prefixPath($path);
        error_clear_last();
        $contents = @file_get_contents($location);

        if ($contents === false) {
            throw ReadFileException::fromLocation($path, error_get_last()['message'] ?? '');
        }

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path): mixed
    {
        $location = $this->prefixer->prefixPath($path);
        error_clear_last();
        $contents = @fopen($location, 'rb');

        if ($contents === false) {
            throw ReadFileException::fromLocation($path, error_get_last()['message'] ?? '');
        }

        return $contents;
    }

    /**
     * Ensure a directory exists, creating it with the given visibility if needed.
     *
     * @param string $dirname
     * @param int $visibility
     * @throws CreateDirectoryException
     */
    protected function ensureDirectoryExists(string $dirname, int $visibility): void
    {
        if (is_dir($dirname)) {
            return;
        }

        error_clear_last();

        if (! @mkdir($dirname, $visibility, true)) {
            $mkdirError = error_get_last();
        }

        clearstatcache(true, $dirname);

        if (! is_dir($dirname)) {
            $errorMessage = isset($mkdirError['message']) ? $mkdirError['message'] : '';

            throw CreateDirectoryException::atLocation($dirname, $errorMessage);
        }
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        $location = $this->prefixer->prefixPath($path);

        return is_file($location);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        $path = $this->prefixer->prefixPath($path);

        return is_dir($path);
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        $this->ensureRootDirectoryExists();
        $location = $this->prefixer->prefixPath($path);

        $visibility = $config[Filesystem::OPTION_VISIBILITY]
            ?? $config[Filesystem::OPTION_DIRECTORY_VISIBILITY];

        $permissions = $this->resolveDirectoryVisibility($visibility);

        if (is_dir($location)) {
            $this->setPermissions($location, $permissions);

            return;
        }

        error_clear_last();

        if (! @mkdir($location, $permissions, true)) {
            throw CreateDirectoryException::atLocation($path, error_get_last()['message'] ?? '');
        }
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $path = $this->prefixer->prefixPath($path);
        $visibility = is_dir($path)
            ? $this->visibility->forDirectory($visibility)
            : $this->visibility->forFile($visibility);

        $this->setPermissions($path, $visibility);
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);
        clearstatcache(false, $location);
        error_clear_last();
        $fileperms = @fileperms($location);

        if ($fileperms === false) {
            throw RetrieveMetadataException::getVisibility($path, error_get_last()['message'] ?? '');
        }

        $permissions = $fileperms & 0777;
        $visibility = $this->visibility->inverseForFile($permissions);

        return new FileAttributes($path, null, $visibility);
    }

    /**
     * Resolve directory visibility, falling back to default.
     *
     * @param string|null $visibility
     * @return int
     */
    private function resolveDirectoryVisibility(?string $visibility): int
    {
        return $visibility === null
            ? $this->visibility->defaultForDirectories()
            : $this->visibility->forDirectory($visibility);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);
        error_clear_last();

        if (! is_file($location)) {
            throw RetrieveMetadataException::mimeType($location, 'No such file exists.');
        }

        $mimeType = $this->mimeTypeDetector->detectMimeTypeFromFile($location);

        if ($mimeType === null) {
            throw RetrieveMetadataException::mimeType($path, error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);
        error_clear_last();
        $lastModified = @filemtime($location);

        if ($lastModified === false) {
            throw RetrieveMetadataException::lastModified($path, error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, null, null, $lastModified);
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): FileAttributes
    {
        $location = $this->prefixer->prefixPath($path);
        error_clear_last();

        if (is_file($location) && ($fileSize = @filesize($location)) !== false) {
            return new FileAttributes($path, $fileSize);
        }

        throw RetrieveMetadataException::size($path, error_get_last()['message'] ?? '');
    }

    /**
     * List contents of a single directory (non-recursive).
     *
     * @param string $location
     * @return Generator<SplFileInfo>
     */
    private function listDirectory(string $location): Generator
    {
        $iterator = new DirectoryIterator($location);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            yield $item;
        }
    }

    /**
     * Set file/directory permissions.
     *
     * @param string $location
     * @param int $visibility
     * @throws SetVisibilityException
     */
    private function setPermissions(string $location, int $visibility): void
    {
        error_clear_last();

        if (! @chmod($location, $visibility)) {
            $extraMessage = error_get_last()['message'] ?? '';
            throw SetVisibilityException::atLocation($this->prefixer->stripPrefix($location), $extraMessage);
        }
    }
}