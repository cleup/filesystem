<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use DateTime;
use Generator;
use Cleup\Filesystem\Finder\DirectoryAttributes;
use Cleup\Filesystem\Finder\FileAttributes;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
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
use Cleup\Filesystem\Interfaces\FtpConnectionProviderInterface;
use Cleup\Filesystem\Interfaces\FtpConnectivityCheckerInterface;
use Cleup\Filesystem\Support\VisibilityConverter;
use Cleup\Filesystem\Interfaces\VisibilityConverterInterface;
use Cleup\Filesystem\Support\MimeType\FinfoMimeTypeDetector;
use Cleup\Filesystem\Interfaces\MimeTypeDetectorInterface;
use Throwable;

use function array_map;
use function error_clear_last;
use function error_get_last;
use function ftp_chdir;
use function ftp_close;
use function is_string;

class FtpAdapter implements AdapterInterface
{
    private const SYSTEM_TYPE_WINDOWS = 'windows';
    private const SYSTEM_TYPE_UNIX = 'unix';

    private FtpConnectionProviderInterface $connectionProvider;
    private FtpConnectivityCheckerInterface $connectivityChecker;

    /**
     * @var resource|false|\FTP\Connection
     */
    private mixed $connection = false;
    private PathPrefixer $prefixer;
    private VisibilityConverterInterface $visibilityConverter;
    private ?bool $isPureFtpdServer = null;
    private ?bool $useRawListOptions;
    private ?string $systemType;
    private bool $finderMimeTypeDetect = false;
    private MimeTypeDetectorInterface $mimeTypeDetector;

    private ?string $rootDirectory = null;

    public function __construct(
        private FtpConnectionOptions $connectionOptions,
        ?FtpConnectionProviderInterface $connectionProvider = null,
        ?FtpConnectivityCheckerInterface $connectivityChecker = null,
        ?VisibilityConverterInterface $visibilityConverter = null,
        ?MimeTypeDetectorInterface $mimeTypeDetector = null,
        $finderMimeTypeDetect = false,
        private bool $detectMimeTypeUsingPath = false,
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


    private function connection()
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

    public function disconnect(): void
    {
        if ($this->hasFtpConnection()) {
            @ftp_close($this->connection);
        }
        $this->connection = false;
    }

    private function isPureFtpdServer(): bool
    {
        if ($this->isPureFtpdServer !== null) {
            return $this->isPureFtpdServer;
        }

        $response = ftp_raw($this->connection, 'HELP');

        return $this->isPureFtpdServer = stripos(implode(' ', $response), 'Pure-FTPd') !== false;
    }

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

    public function fileExists($path): bool
    {
        try {
            $this->size($path);

            return true;
        } catch (RetrieveMetadataException $exception) {
            return false;
        }
    }

    public function put($path, $contents, $config = []): void
    {
        try {
            $writeStream = fopen('php://temp', 'w+b');
            fwrite($writeStream, $contents);
            rewind($writeStream);
            $this->writeStream($path, $writeStream, $config);
        } finally {
            isset($writeStream) && is_resource($writeStream) && fclose($writeStream);
        }
    }

    public function writeStream($path, $contents, $config = []): void
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

        if (! $visibility = ($config[Filesystem::OPTION_VISIBILITY] ?? null)) {
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

    public function get($path): string
    {
        $readStream = $this->readStream($path);
        $contents = stream_get_contents($readStream);
        fclose($readStream);

        return $contents;
    }

    public function readStream($path)
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

    public function delete($path): void
    {
        $connection = $this->connection();
        $this->deleteFile($path, $connection);
    }


    private function deleteFile(string $path, $connection): void
    {
        $location = $this->prefixer()->prefixPath($path);
        $success = @ftp_delete($connection, $location);

        if ($success === false && ftp_size($connection, $location) !== -1) {
            throw DeleteFileException::atLocation($path, 'the file still exists');
        }
    }

    public function deleteDirectory($path): void
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

    public function createDirectory($path, $config = []): void
    {
        $this->ensureDirectoryExists(
            $path,
            $config[Filesystem::OPTION_DIRECTORY_VISIBILITY]
                ?? $config[Filesystem::OPTION_VISIBILITY]
        );
    }

    public function setVisibility($path, $visibility): void
    {
        $location = $this->prefixer()->prefixPath($path);
        $mode = $this->visibilityConverter->forFile($visibility);

        if (! @ftp_chmod($this->connection(), $mode, $location)) {
            $message = error_get_last()['message'] ?? '';
            throw SetVisibilityException::atLocation($path, $message);
        }
    }

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
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();
        $lastModified = @ftp_mdtm($connection, $location);

        if ($lastModified < 0) {
            throw RetrieveMetadataException::lastModified($path);
        }

        return new FileAttributes($path, null, null, $lastModified);
    }

    public function getVisibility($path): FileAttributes
    {
        return $this->fetchMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function size($path): FileAttributes
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();
        $fileSize = @ftp_size($connection, $location);

        if ($fileSize < 0) {
            throw RetrieveMetadataException::size($path, error_get_last()['message'] ?? '');
        }

        return new FileAttributes($path, $fileSize);
    }

    public function finder($path, $deep): iterable
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

    private function normalizeObject(string $item, string $base): FinderAttributesInterface
    {
        $this->systemType === null && $this->systemType = $this->detectSystemType($item);

        if ($this->systemType === self::SYSTEM_TYPE_UNIX) {
            return $this->normalizeUnixObject($item, $base);
        }

        return $this->normalizeWindowsObject($item, $base);
    }

    private function detectSystemType(string $item): string
    {
        return preg_match(
            '/^[0-9]{2,4}-[0-9]{2}-[0-9]{2}/',
            $item
        ) ? self::SYSTEM_TYPE_WINDOWS : self::SYSTEM_TYPE_UNIX;
    }

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

        // Check for the correct date/time format
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
        $lastModified = $this->connectionOptions->timestampsOnUnixListingsEnabled() ? $this->normalizeUnixTimestamp(
            $month,
            $day,
            $timeOrYear
        ) : null;

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

    private function listingItemIsDirectory(string $permissions): bool
    {
        return str_starts_with($permissions, 'd');
    }

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

    private function normalizePermissions(string $permissions): int
    {
        // remove the type identifier
        $permissions = substr($permissions, 1);

        // map the string rights to the numeric counterparts
        $map = ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1'];
        $permissions = strtr($permissions, $map);

        // split up the permission groups
        $parts = str_split($permissions, 3);

        // convert the groups
        $mapper = static function ($part) {
            return array_sum(array_map(static function ($p) {
                return (int) $p;
            }, str_split($part)));
        };

        // converts to decimal number
        return octdec(implode('', array_map($mapper, $parts)));
    }

    private function listDirectoryContentsRecursive(string $directory): Generator
    {
        $location = $this->prefixer()->prefixPath($directory);
        $listing = $this->ftpRawlist('-aln', $location);
        /** @var StorageAttributes[] $listing */
        $listing = $this->normalizeListing($listing, $directory);

        foreach ($listing as $item) {
            yield $item;

            if (! $item->isDir()) {
                continue;
            }

            $children = $this->listDirectoryContentsRecursive($item->path());

            foreach ($children as $child) {
                yield $child;
            }
        }
    }

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

        return ftp_rawlist($connection, ($options ? $options . ' ' : '') . $path, stripos($options, 'R') !== false) ?: [];
    }

    public function move($from, $to, $config = []): void
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

    private function ensureParentDirectoryExists(string $path, ?string $visibility): void
    {
        $dirname = dirname($path);

        if ($dirname === '' || $dirname === '.') {
            return;
        }

        $this->ensureDirectoryExists($dirname, $visibility);
    }

    private function ensureDirectoryExists(string $dirname, ?string $visibility): void
    {
        $connection = $this->connection();

        $dirPath = '';
        $parts = explode('/', trim($dirname, '/'));
        $mode = $visibility ? $this->visibilityConverter->forDirectory($visibility) : false;

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

    private function escapePath(string $path): string
    {
        return str_replace(['*', '[', ']'], ['\\*', '\\[', '\\]'], $path);
    }

    /**
     * @return bool
     */
    private function hasFtpConnection(): bool
    {
        return $this->connection instanceof \FTP\Connection || is_resource($this->connection);
    }

    public function directoryExists($path): bool
    {
        $location = $this->prefixer()->prefixPath($path);
        $connection = $this->connection();

        return @ftp_chdir($connection, $location) === true;
    }

    /**
     * @param resource|\FTP\Connection $connection
     */
    private function resolveConnectionRoot($connection): string
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
     * @return PathPrefixer
     */
    private function prefixer(): PathPrefixer
    {
        if ($this->rootDirectory === null) {
            $this->connection();
        }

        return $this->prefixer;
    }
}
