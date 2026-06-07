<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\FtpInvalidListResponseReceivedException;
use Cleup\Filesystem\Exceptions\FtpResolveConnectionRootException;
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
use Cleup\Filesystem\Interfaces\FtpConnectionProviderInterface;
use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;
use Cleup\Filesystem\Support\MimeType\FinfoMimeTypeDetector;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Support\VisibilityConverter;
use DateTime;
use Generator;
use Throwable;

use function array_map;
use function error_clear_last;
use function error_get_last;
use function ftp_chdir;
use function ftp_close;
use function is_string;

/**
 * FTP adapter for file upload/download operations.
 * Provides FTP/SFTP filesystem interaction through the unified AdapterInterface.
 *
 * @inheritDoc
 */
class FtpAdapter implements AdapterInterface
{
    private const SYSTEM_TYPE_WINDOWS = 'windows';
    private const SYSTEM_TYPE_UNIX = 'unix';

    private FtpConnectionProviderInterface $connectionProvider;
    private FtpConnectivityCheckerInterface $connectivityChecker;

    /** @var resource|\FTP\Connection|false */
    private mixed $connection = false;

    private PathPrefixer $prefixer;
    private VisibilityConverterInterface $visibilityConverter;
    private ?bool $isPureFtpdServer = null;
    private ?bool $useRawListOptions;
    private ?string $systemType;
    private bool $finderMimeTypeDetect = false;
    private MimeTypeDetectorInterface $mimeTypeDetector;
    private ?string $rootDirectory = null;

    /**
     * @param FtpConnectionOptions $connectionOptions FTP connection configuration.
     * @param FtpConnectionProviderInterface|null $connectionProvider Factory for creating FTP connections.
     * @param FtpConnectivityCheckerInterface|null $connectivityChecker Checks if the connection is still alive.
     * @param VisibilityConverterInterface|null $visibilityConverter Converts between string and numeric permissions.
     * @param MimeTypeDetectorInterface|null $mimeTypeDetector Detects MIME types for files.
     * @param bool $finderMimeTypeDetect Whether to detect MIME types during directory listing.
     * @param bool $detectMimeTypeUsingPath Whether to detect MIME types by path instead of content.
     */
    public function __construct(
        private readonly FtpConnectionOptions $connectionOptions,
        ?FtpConnectionProviderInterface $connectionProvider = null,
        ?FtpConnectivityCheckerInterface $connectivityChecker = null,
        ?VisibilityConverterInterface $visibilityConverter = null,
        ?MimeTypeDetectorInterface $mimeTypeDetector = null,
        bool $finderMimeTypeDetect = false,
        private readonly bool $detectMimeTypeUsingPath = false,
    ) {
        $this->systemType = $this->connectionOptions->systemType();
        $this->connectionProvider = $connectionProvider ?? new FtpConnectionProvider();
        $this->connectivityChecker = $connectivityChecker ?? new NoopCommandConnectivityChecker();
        $this->visibilityConverter = $visibilityConverter ?? new VisibilityConverter();
        $this->mimeTypeDetector = $mimeTypeDetector ?? new FinfoMimeTypeDetector();
        $this->useRawListOptions = $connectionOptions->useRawListOptions();
        $this->finderMimeTypeDetect = $finderMimeTypeDetect;
    }

    /**
     * Disconnect FTP connection on destruct.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Get or create an active FTP connection, reconnecting if necessary.
     *
     * @return resource|\FTP\Connection
     */
    private function connection(): mixed
    {
        start:
        if (! $this->hasFtpConnection()) {
            $this->connection = $this->connectionProvider->createConnection($this->connectionOptions);
            $this->rootDirectory = $this->resolveConnectionRoot($this->connection);
            $this->prefixer = new PathPrefixer($this->rootDirectory);

            return $this->connection;
        }

        if ($this->connectivityChecker->isConnected($this->connection) === false) {
            $this->connection = false;
            goto start;
        }

        ftp_chdir($this->connection, $this->rootDirectory);

        return $this->connection;
    }

    /**
     * Close the active FTP connection.
     */
    public function disconnect(): void
    {
        if ($this->hasFtpConnection()) {
            @ftp_close($this->connection);
        }
        $this->connection = false;
    }

    /**
     * Check if the FTP server is Pure-FTPd.
     */
    private function isPureFtpdServer(): bool
    {
        if ($this->isPureFtpdServer !== null) {
            return $this->isPureFtpdServer;
        }

        $response = ftp_raw($this->connection, 'HELP');

        return $this->isPureFtpdServer = stripos(implode(' ', $response), 'Pure-FTPd') !== false;
    }

    /**
     * Check if the FTP server supports raw list options.
     */
    private function isServerSupportingListOptions(): bool
    {
        if ($this->useRawListOptions !== null) {
            return $this->useRawListOptions;
        }

        $response = ftp_raw($this->connection, 'SYST');
        $syst = implode(' ', $response);

        return $this->useRawListOptions = stripos($syst, 'FileZilla') === false
            && stripos($syst, 'L8') === false;
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        try {
            $this->size($path);

            return true;
        } catch (RetrieveMetadataException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, mixed $contents, array $config = []): void
    {
        try {
            $writeStream = fopen('php://temp', 'w+b');
            fwrite($writeStream, $contents);
            rewind($writeStream);
            $this->writeStream($path, $writeStream, $config);
        } finally {
            if (isset($writeStream) && is_resource($writeStream)) {
                fclose($writeStream);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, mixed $contents, array $config = []): void
    {
        try {
            $this->ensureParentDirectoryExists(
                $path,
                $config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null
            );
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation(
                $path,
                'creating parent directory failed',
                $exception
            );
        }

        $location = $this->prefixer()->prefixPath($path);

        if (! ftp_fput($this->connection(), $location, $contents, $this->connectionOptions->transferMode())) {
            throw WriteFileException::atLocation($path, 'writing the file failed');
        }

        $visibility = $config[Filesystem::OPTION_VISIBILITY] ?? null;

        if ($visibility === null) {
            return;
        }

        try {
            $this->setVisibility($path, $visibility);
        } catch (Throwable $exception) {
            throw WriteFileException::atLocation(
                $path,
                'setting visibility failed',
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        $readStream = $this->readStream($path);
        $contents = stream_get_contents($readStream);
        fclose($readStream);

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path): mixed
    {
        $location = $this->prefixer()->prefixPath($path);
        $stream = fopen('php://temp', 'w+b');
        $result = @ftp_fget(
            $this->connection(),
            $stream,
            $location,
            $this->connectionOptions->transferMode()
        );

        if (! $result) {
            fclose($stream);

            throw ReadFileException::fromLocation($path, error_get_last()['message'] ?? '');
        }

        rewind($stream);

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $connection = $this->connection();
        $this->deleteFile($path, $connection);
    }

    /**
     * Delete a single file from the FTP server.
     *
     * @param string $path File path relative to the root.
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @throws DeleteFileException
     */
    private function deleteFile(string $path, mixed $connection): void
    {
        $location = $this->prefixer()->prefixPath($path);
        $success = @ftp_delete($connection, $location);

        if ($success === false && ftp_size($connection, $location) !== -1) {
            throw DeleteFileException::atLocation($path, 'the file still exists');
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        /** @var FinderAttributesInterface[] $contents */
        $contents = $this->finder($path, true);
        $connection = $this->connection();
        $directories = [$path];

        foreach ($contents as $item) {
            if ($item->isDir()) {
                $directories[] = $item->path();
                continue;
            }
            try {
                $this->deleteFile($item->path(), $connection);
            } catch (Throwable $exception) {
                throw DeleteDirectoryException::atLocation($path, 'unable to delete child', $exception);
            }
        }

        rsort($directories);

        foreach ($directories as $directory) {
            if (! @ftp_rmdir($connection, $this->prefixer()->prefixPath($directory))) {
                throw DeleteDirectoryException::atLocation($path, "Could not delete directory $directory");
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, array $config = []): void
    {
        $this->ensureDirectoryExists(
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
        $location = $this->prefixer()->prefixPath($path);
        $mode = $this->visibilityConverter->forFile($visibility);

        if (! @ftp_chmod($this->connection(), $mode, $location)) {
            $message = error_get_last()['message'] ?? '';
            throw SetVisibilityException::atLocation($path, $message);
        }
    }

    /**
     * Fetch file/directory metadata using FTP STAT command.
     *
     * @param string $path Path to the file or directory.
     * @param string $type Metadata type being requested.
     * @return FileAttributes
     * @throws RetrieveMetadataException
     */
    private function fetchMetadata(string $path, string $type): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);

        if ($this->isPureFtpdServer) {
            $location = $this->escapePath($location);
        }

        $object = @ftp_raw($this->connection(), 'STAT ' . $location);

        if (empty($object) || count($object) < 3 || str_starts_with($object[1], "ftpd:")) {
            throw RetrieveMetadataException::create($path, $type, error_get_last()['message'] ?? '');
        }

        $attributes = $this->normalizeObject($object[1], '');

        if (! $attributes instanceof FileAttributes) {
            throw RetrieveMetadataException::create(
                $path,
                $type,
                'expected file, ' . ($attributes instanceof DirectoryAttributes ? 'directory found' : 'nothing found')
            );
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
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();
        $lastModified = @ftp_mdtm($connection, $location);

        if ($lastModified < 0) {
            throw RetrieveMetadataException::lastModified($path);
        }

        return new FileAttributes($path, null, null, $lastModified);
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(string $path): FileAttributes
    {
        return $this->fetchMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();
        $fileSize = @ftp_size($connection, $location);

        if ($fileSize < 0) {
            throw RetrieveMetadataException::size($path, error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, $fileSize);
    }

    /**
     * @inheritDoc
     */
    public function finder(string $path, bool $deep): Generator
    {
        $path = ltrim($path, '/');
        $path = $path === '' ? $path : trim($path, '/') . '/';

        if ($deep && $this->connectionOptions->recurseManually()) {
            yield from $this->listDirectoryContentsRecursive($path);
        } else {
            $location = $this->prefixer()->prefixPath($path);
            $options = $deep ? '-alnR' : '-aln';
            $listing = $this->ftpRawlist($options, $location);
            yield from $this->normalizeListing($listing, $path);
        }
    }

    /**
     * Normalize raw FTP listing into FinderAttributesInterface objects.
     *
     * @param array<int, string> $listing Raw FTP listing lines.
     * @param string $prefix Base path prefix for the listed items.
     * @return Generator
     */
    private function normalizeListing(array $listing, string $prefix = ''): Generator
    {
        $base = $prefix;

        foreach ($listing as $item) {
            if ($item === '' || preg_match('#.* \.(\.)?$|^total#', $item)) {
                continue;
            }

            if (preg_match('#^.*:$#', $item)) {
                $base = preg_replace('~^\./*|:$~', '', $item);
                continue;
            }

            yield $this->normalizeObject($item, $base);
        }
    }

    /**
     * Normalize a single FTP listing item into a file or directory attributes object.
     *
     * @param string $item Raw listing line.
     * @param string $base Base directory path.
     * @return FinderAttributesInterface
     * @throws FtpInvalidListResponseReceivedException
     */
    private function normalizeObject(string $item, string $base): FinderAttributesInterface
    {
        if ($this->systemType === null) {
            $this->systemType = $this->detectSystemType($item);
        }

        if ($this->systemType === self::SYSTEM_TYPE_UNIX) {
            return $this->normalizeUnixObject($item, $base);
        }

        return $this->normalizeWindowsObject($item, $base);
    }

    /**
     * Detect whether the FTP server uses Windows or Unix style listings.
     */
    private function detectSystemType(string $item): string
    {
        return preg_match(
            '/^[0-9]{2,4}-[0-9]{2}-[0-9]{2}/',
            $item
        ) ? self::SYSTEM_TYPE_WINDOWS : self::SYSTEM_TYPE_UNIX;
    }

    /**
     * Normalize a Windows-style FTP listing item.
     */
    private function normalizeWindowsObject(string $item, string $base): FinderAttributesInterface
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 3);
        $parts = explode(' ', $item, 4);

        if (count($parts) !== 4) {
            throw new FtpInvalidListResponseReceivedException("Metadata can't be parsed from item '$item' , not enough parts.");
        }

        [$date, $time, $size, $name] = $parts;
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;

        if ($size === '<DIR>') {
            return new DirectoryAttributes($path);
        }

        $format = strlen($date) === 8 ? 'm-d-yH:iA' : 'Y-m-dH:i';
        $dt = DateTime::createFromFormat($format, $date . $time);
        $lastModified = $dt ? $dt->getTimestamp() : (int) strtotime("$date $time");

        return new FileAttributes(
            $path,
            (int) $size,
            null,
            $lastModified,
            $this->finderMimeTypeDetect
                ? $this->mimeTypeDetector->detectMimeTypeFromPath($path)
                : null
        );
    }

    /**
     * Normalize a Unix-style FTP listing item.
     */
    private function normalizeUnixObject(string $item, string $base): FinderAttributesInterface
    {
        $item = preg_replace('#\s+#', ' ', trim($item), 7);
        $parts = explode(' ', $item, 9);

        if (count($parts) !== 9) {
            throw new FtpInvalidListResponseReceivedException("Metadata can't be parsed from item '$item' , not enough parts.");
        }

        [$permissions, /* $number */, /* $owner */, /* $group */, $size, $month, $day, $timeOrYear, $name] = $parts;
        $isDirectory = $this->listingItemIsDirectory($permissions);
        $permissions = $this->normalizePermissions($permissions);
        $path = $base === '' ? $name : rtrim($base, '/') . '/' . $name;
        $lastModified = $this->connectionOptions->timestampsOnUnixListingsEnabled()
            ? $this->normalizeUnixTimestamp($month, $day, $timeOrYear)
            : null;

        if ($isDirectory) {
            return new DirectoryAttributes(
                $path,
                $this->visibilityConverter->inverseForDirectory($permissions),
                $lastModified
            );
        }

        $visibility = $this->visibilityConverter->inverseForFile($permissions);

        return new FileAttributes(
            $path,
            (int) $size,
            $visibility,
            $lastModified,
            $this->finderMimeTypeDetect
                ? $this->mimeTypeDetector->detectMimeTypeFromPath($path)
                : null
        );
    }

    /**
     * Check if a Unix permissions string indicates a directory.
     */
    private function listingItemIsDirectory(string $permissions): bool
    {
        return str_starts_with($permissions, 'd');
    }

    /**
     * Convert Unix listing date/time parts to a Unix timestamp.
     */
    private function normalizeUnixTimestamp(string $month, string $day, string $timeOrYear): int
    {
        if (is_numeric($timeOrYear)) {
            $year = $timeOrYear;
            $hour = '00';
            $minute = '00';
        } else {
            $year = date('Y');
            [$hour, $minute] = explode(':', $timeOrYear);
        }

        $dateTime = DateTime::createFromFormat('Y-M-j-G:i:s', "$year-$month-$day-$hour:$minute:00");

        return $dateTime->getTimestamp();
    }

    /**
     * Convert Unix permission string (e.g., "rwxr-xr-x") to octal integer (e.g., 0755).
     */
    private function normalizePermissions(string $permissions): int
    {
        $permissions = substr($permissions, 1);

        $map = ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1'];
        $permissions = strtr($permissions, $map);

        $parts = str_split($permissions, 3);

        $mapper = static fn(string $part): int => array_sum(
            array_map(static fn(string $p): int => (int) $p, str_split($part))
        );

        return octdec(implode('', array_map($mapper, $parts)));
    }

    /**
     * Recursively list directory contents for servers that don't support recursive listing.
     *
     * @return Generator
     */
    private function listDirectoryContentsRecursive(string $directory): Generator
    {
        $location = $this->prefixer()->prefixPath($directory);
        $listing = $this->ftpRawlist('-aln', $location);
        $listing = $this->normalizeListing($listing, $directory);

        foreach ($listing as $item) {
            yield $item;

            if (! $item->isDir()) {
                continue;
            }

            yield from $this->listDirectoryContentsRecursive($item->path());
        }
    }

    /**
     * Execute FTP raw list command with proper escaping and option handling.
     *
     * @param string $options List command options (e.g., '-alnR').
     * @param string $path Directory path to list.
     * @return array<int, string>
     */
    private function ftpRawlist(string $options, string $path): array
    {
        $path = rtrim($path, '/') . '/';
        $connection = $this->connection();

        if ($this->isPureFtpdServer()) {
            $path = str_replace(' ', '\ ', $path);
            $path = $this->escapePath($path);
        }

        if (! $this->isServerSupportingListOptions()) {
            $options = '';
        }

        return ftp_rawlist(
            $connection,
            ($options !== '' ? $options . ' ' : '') . $path,
            stripos($options, 'R') !== false
        ) ?: [];
    }

    /**
     * @inheritDoc
     */
    public function move(string $from, string $to, array $config = []): void
    {
        try {
            $this->ensureParentDirectoryExists(
                $to,
                $config[Filesystem::OPTION_DIRECTORY_VISIBILITY] ?? null
            );
        } catch (Throwable $exception) {
            throw MoveFileException::fromLocationTo($from, $to, $exception);
        }

        $sourceLocation = $this->prefixer()->prefixPath($from);
        $destinationLocation = $this->prefixer()->prefixPath($to);
        $connection = $this->connection();

        if (! @ftp_rename($connection, $sourceLocation, $destinationLocation)) {
            throw MoveFileException::because(error_get_last()['message'] ?? 'reason unknown', $from, $to);
        }
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

    /**
     * Ensure the parent directory of a file path exists, creating it if needed.
     */
    private function ensureParentDirectoryExists(string $path, ?string $visibility): void
    {
        $dirname = dirname($path);

        if ($dirname === '' || $dirname === '.') {
            return;
        }

        $this->ensureDirectoryExists($dirname, $visibility);
    }

    /**
     * Ensure a directory exists, creating all parent directories and setting permissions.
     *
     * @throws CreateDirectoryException
     */
    private function ensureDirectoryExists(string $dirname, ?string $visibility): void
    {
        $connection = $this->connection();

        $dirPath = '';
        $parts = explode('/', trim($dirname, '/'));
        $mode = $visibility !== null ? $this->visibilityConverter->forDirectory($visibility) : false;

        foreach ($parts as $part) {
            $dirPath .= '/' . $part;
            $location = $this->prefixer()->prefixPath($dirPath);

            if (@ftp_chdir($connection, $location)) {
                continue;
            }

            error_clear_last();
            $result = @ftp_mkdir($connection, $location);

            if ($result === false) {
                $errorMessage = error_get_last()['message'] ?? 'unable to create the directory';
                throw CreateDirectoryException::atLocation($dirPath, $errorMessage);
            }

            if ($mode !== false && @ftp_chmod($connection, $mode, $location) === false) {
                throw CreateDirectoryException::atLocation(
                    $dirPath,
                    'unable to chmod the directory: ' . (error_get_last()['message'] ?? 'reason unknown'),
                );
            }
        }
    }

    /**
     * Escape special characters in FTP paths (wildcards and brackets).
     */
    private function escapePath(string $path): string
    {
        return str_replace(['*', '[', ']'], ['\\*', '\\[', '\\]'], $path);
    }

    /**
     * Check if there is an active FTP connection.
     */
    private function hasFtpConnection(): bool
    {
        return $this->connection instanceof \FTP\Connection || is_resource($this->connection);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();

        return @ftp_chdir($connection, $location);
    }

    /**
     * Resolve the connection root directory and verify it exists.
     *
     * @param resource|\FTP\Connection $connection Active FTP connection.
     * @return string The resolved root directory path.
     * @throws FtpResolveConnectionRootException
     */
    private function resolveConnectionRoot(mixed $connection): string
    {
        $root = $this->connectionOptions->root();
        error_clear_last();

        if ($root !== '' && @ftp_chdir($connection, $root) !== true) {
            throw FtpResolveConnectionRootException::itDoesNotExist($root, error_get_last()['message'] ?? '');
        }

        error_clear_last();
        $pwd = @ftp_pwd($connection);

        if (! is_string($pwd)) {
            throw FtpResolveConnectionRootException::couldNotGetCurrentDirectory(error_get_last()['message'] ?? '');
        }

        return $pwd;
    }

    /**
     * Get the path prefixer, initializing the connection if needed.
     */
    private function prefixer(): PathPrefixer
    {
        if ($this->rootDirectory === null) {
            $this->connection();
        }

        return $this->prefixer;
    }
}
