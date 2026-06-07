<?php

declare(strict_types=1);

namespace Cleup\Filesystem;

use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\FinderException;
use Cleup\Filesystem\Exceptions\InvalidChecksumAlgoException;
use Cleup\Filesystem\Exceptions\InvalidStreamProvidedException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Finder\Finder;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Interfaces\DriverInterface;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Cleup\Filesystem\Interfaces\FinderFileAttributesInterface;
use Cleup\Filesystem\Support\Path;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Support\VisibilityConverter;
use Generator;
use Psr\Http\Message\StreamInterface;
use Throwable;

use function array_key_exists;

/**
 * Base driver for file upload/download operations.
 * Wraps an adapter with path normalization, configuration, and error handling.
 */
abstract class Driver implements DriverInterface
{
    /** @var string */
    public const STRATEGY_IGNORE = 'ignore';

    /** @var string */
    public const STRATEGY_FAIL = 'fail';

    /** @var string */
    public const STRATEGY_TRY = 'try';

    protected AdapterInterface $adapter;
    protected PathPrefixer $prefixer;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Driver configuration.
     * @param bool $debug Enable debug/exception mode.
     */
    public function __construct(
        array $config = [],
        protected bool $debug = false,
    ) {
        $separator = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;

        $this->prefixer = new PathPrefixer(
            $config['root'] ?? '',
            $separator
        );

        if (isset($config['prefix'])) {
            $this->prefixer = new PathPrefixer(
                $this->prefixer->prefixPath($config['prefix']),
                $separator
            );
        }

        $defaultConfig = $this->configure() ?? [];

        if ($defaultConfig !== []) {
            $config = array_merge($defaultConfig, $config);
        }

        $this->config = $config;
        $this->mountDriver();
    }

    /**
     * Configure default options for the driver.
     * Override in child classes to provide driver-specific defaults.
     *
     * @return array<string, mixed>|null
     */
    protected function configure(): ?array
    {
        return [];
    }

    /**
     * Create the adapter instance for this driver.
     * Override in child classes to instantiate the specific adapter.
     *
     * @return AdapterInterface
     */
    abstract protected function create(): AdapterInterface;

    /**
     * @inheritDoc
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Check if debug mode is enabled.
     */
    protected function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Create or retrieve a visibility converter.
     *
     * @param VisibilityConverter|null $converter Optional existing converter.
     * @return VisibilityConverter
     */
    protected function visibilityConverter(?VisibilityConverter $converter = null): VisibilityConverter
    {
        return $converter ?? VisibilityConverter::fromArray(
            $this->getConfig('permissions', []),
            $this->getConfig(Filesystem::OPTION_DIRECTORY_VISIBILITY)
                ?? $this->getConfig(Filesystem::OPTION_VISIBILITY)
                ?? Filesystem::VISIBILITY_PRIVATE
        );
    }

    /**
     * Filter array to only specified keys.
     *
     * @param array<string, mixed> $arr
     * @param array<int, string> $items
     * @return array<string, mixed>
     */
    protected function onlyArrayItems(array $arr, array $items = []): array
    {
        return array_intersect_key(
            $arr,
            array_flip($items)
        );
    }

    /**
     * Get root configuration options.
     *
     * @return array<string, mixed>
     */
    private function getRootOptions(): array
    {
        return $this->onlyArrayItems(
            $this->config,
            [
                Filesystem::OPTION_COPY_IDENTICAL_PATH,
                Filesystem::OPTION_MOVE_IDENTICAL_PATH,
                Filesystem::OPTION_VISIBILITY,
                Filesystem::OPTION_DIRECTORY_VISIBILITY,
                Filesystem::OPTION_RETAIN_VISIBILITY,
            ]
        );
    }

    /**
     * Exclude specified options from the root configuration.
     *
     * @param string ...$options Option keys to exclude.
     * @return array<string, mixed>
     */
    public function excludeOptions(string ...$options): array
    {
        return array_diff_key(
            $this->getRootOptions(),
            array_flip($options)
        );
    }

    /**
     * Merge custom options with root configuration.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function combiningOptions(array $options): array
    {
        return array_merge(
            $this->getRootOptions(),
            $options
        );
    }

    /**
     * Mount the adapter driver.
     */
    private function mountDriver(): void
    {
        $adapter = $this->create();

        $this->adapter = $adapter;
    }

    /**
     * @inheritDoc
     */
    public function path(string $path, bool $normalize = false): string
    {
        $fullPath = $this->prefixer->prefixPath($path);

        if ($normalize) {
            return Path::normalizePath($fullPath);
        }

        return $fullPath;
    }

    /**
     * @inheritDoc
     */
    public function name(string $path, bool $pathPrefix = true): string
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_FILENAME
        );
    }

    /**
     * @inheritDoc
     */
    public function basename(string $path, bool $pathPrefix = true): string
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_BASENAME
        );
    }

    /**
     * @inheritDoc
     */
    public function dirname(string $path, bool $pathPrefix = true): string
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_DIRNAME
        );
    }

    /**
     * @inheritDoc
     */
    public function extension(string $path, bool $pathPrefix = true): string
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_EXTENSION
        );
    }

    /**
     * @inheritDoc
     */
    public function uniqueHashedName(string $path, bool $isExtension = true): string
    {
        $name = $this->name($path);
        $extension = $this->extension($path);

        return md5($name) . uniqid() . ($isExtension ? '.' . $extension : '');
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        $path = Path::normalizePath($path);

        return $this->adapter->fileExists($path)
            || $this->adapter->directoryExists($path);
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists(
            Path::normalizePath($path)
        );
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists(
            Path::normalizePath($path)
        );
    }

    /**
     * @inheritDoc
     */
    public function get(string $path): string
    {
        return $this->adapter->get(
            Path::normalizePath($path)
        );
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path): mixed
    {
        return $this->adapter->readStream(
            Path::normalizePath($path)
        );
    }

    /**
     * @inheritDoc
     */
    public function json(string $path, int $flags = 0): ?array
    {
        $content = $this->get($path);

        if ($content === null) {
            return null;
        }

        return json_decode($content, true, 512, $flags);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): int
    {
        return $this->adapter->lastModified(
            Path::normalizePath($path)
        )->lastModified();
    }

    /**
     * @inheritDoc
     */
    public function size(string $path): int
    {
        return $this->adapter->size(
            Path::normalizePath($path)
        )->size();
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): string|false
    {
        try {
            return $this->adapter->mimeType(
                Path::normalizePath($path)
            )->mimeType();
        } catch (RetrieveMetadataException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function checksum(string $path, array $config = []): string|false
    {
        $config = $this->combiningOptions($config);

        try {
            $stream = $this->readStream($path);
            $algo = (string) ($config['checksum_algo'] ?? 'md5');
            $context = hash_init($algo);
            hash_update_stream($context, $stream);

            return hash_final($context);
        } catch (InvalidChecksumAlgoException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function finder(string $path, bool $deep = false): Finder
    {
        $path = Path::normalizePath($path);
        $listing = $this->adapter->finder($path, $deep);

        return new Finder(
            $this->finderGenerator($path, $deep, $listing)
        );
    }

    /**
     * Wrap the adapter listing in a generator with error handling.
     *
     * @param string $path
     * @param bool $deep
     * @param Generator $listing
     * @return Generator
     */
    private function finderGenerator(string $path, bool $deep, Generator $listing): Generator
    {
        try {
            foreach ($listing as $item) {
                yield $item;
            }
        } catch (Throwable $exception) {
            throw FinderException::atLocation($path, $deep, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function files(?string $directory = null, bool $recursive = false): array
    {
        return $this->finder($directory ?? '', $recursive)
            ->filter(fn(FinderFileAttributesInterface $attributes): bool => $attributes->isFile())
            ->sortByPath()
            ->map(fn(FinderFileAttributesInterface $attributes): string => $attributes->path())
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function directories(?string $directory = null, bool $recursive = false): array
    {
        return $this->finder($directory ?? '', $recursive)
            ->filter(fn(FinderAttributesInterface $attributes): bool => $attributes->isDir())
            ->map(fn(FinderAttributesInterface $attributes): string => $attributes->path())
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function fileMap(?string $directory = null, bool $recursive = false): array
    {
        return $this->finder($directory ?? '', $recursive)
            ->map(function (FinderAttributesInterface|FinderFileAttributesInterface $attributes) use ($recursive): array {
                $item = [
                    'name' => $this->name($attributes->path()),
                    'type' => $attributes->isDir() ? 'directory' : 'file',
                    'path' => $attributes->path(),
                    'fullPath' => $this->path($attributes->path()),
                    'lastModified' => $attributes->lastModified(),
                ];

                if ($attributes->isFile()) {
                    $item['size'] = $attributes->size();
                    $item['mimeType'] = $attributes->mimeType();
                }

                if ($recursive && $attributes->isDir()) {
                    $item['list'] = $this->fileMap($attributes->path(), $recursive);
                }

                return $item;
            })->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getVisibility(string $path): string
    {
        return $this->adapter->getVisibility(
            Path::normalizePath($path)
        )->getVisibility();
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        try {
            $this->adapter->setVisibility(
                Path::normalizePath($path),
                $visibility
            );

            return true;
        } catch (SetVisibilityException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function put(string $path, mixed $contents, array|string $config = []): bool
    {
        $config = is_string($config)
            ? ['visibility' => $config]
            : (array) $config;

        $config = $this->combiningOptions($config);

        try {
            if ($contents instanceof StreamInterface) {
                $this->adapter->writeStream($path, $contents->detach(), $config);

                return true;
            }

            if (is_resource($contents)) {
                $this->adapter->writeStream($path, $contents, $config);
            } else {
                $this->adapter->put(
                    Path::normalizePath($path),
                    $contents,
                    $config
                );
            }

            return true;
        } catch (WriteFileException | SetVisibilityException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, mixed $contents, array $config = []): bool
    {
        if ($this->isDebug()) {
            if (! is_resource($contents)) {
                throw new InvalidStreamProvidedException(
                    "Invalid stream provided, expected stream resource, received " . gettype($contents)
                );
            }

            if (get_resource_type($contents) !== 'stream') {
                throw new InvalidStreamProvidedException(
                    "Invalid stream provided, expected stream resource, received resource of type " . get_resource_type($contents)
                );
            }
        }

        if (ftell($contents) !== 0 && stream_get_meta_data($contents)['seekable']) {
            rewind($contents);
        }

        try {
            $this->adapter->writeStream(
                Path::normalizePath($path),
                $contents,
                $this->combiningOptions($config)
            );

            return true;
        } catch (WriteFileException | SetVisibilityException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL, array $config = []): bool
    {
        if ($this->fileExists($path)) {
            return $this->put(
                $path,
                $data . $separator . $this->get($path),
                $config
            );
        }

        return $this->put($path, $data, $config);
    }

    /**
     * @inheritDoc
     */
    public function append(string $path, string $data, string $separator = PHP_EOL, array $config = []): bool
    {
        if ($this->fileExists($path)) {
            return $this->put(
                $path,
                $this->get($path) . $separator . $data,
                $config
            );
        }

        return $this->put($path, $data, $config);
    }

    /**
     * @inheritDoc
     */
    public function replaceInFile(array|string $search, array|string $replace, string $path, array $config = []): bool
    {
        return $this->put(
            $path,
            str_replace($search, $replace, $this->get($path)),
            $config
        );
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, array $config = []): bool
    {
        try {
            $this->adapter->createDirectory(
                Path::normalizePath($path),
                $this->combiningOptions($config)
            );

            return true;
        } catch (CreateDirectoryException | SetVisibilityException $exception) {
            if ($this->isDebug()) {
                throw $exception;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): bool
    {
        try {
            $this->adapter->deleteDirectory(
                Path::normalizePath($path)
            );
        } catch (DeleteDirectoryException $e) {
            if ($this->debug) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string|array $path): bool
    {
        $paths = is_array($path) ? $path : func_get_args();
        $success = true;

        foreach ($paths as $item) {
            try {
                $this->adapter->delete(
                    Path::normalizePath($item)
                );
            } catch (DeleteFileException $exception) {
                if ($this->isDebug()) {
                    throw $exception;
                }

                $success = false;
            }
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    public function move(string $from, string $to, array $config = []): bool
    {
        try {
            $config = $this->combiningMoveAndCopyOptions($config);
            $from = Path::normalizePath($from);
            $to = Path::normalizePath($to);

            if ($from === $to) {
                $strategy = $config[Filesystem::OPTION_MOVE_IDENTICAL_PATH] ?? static::STRATEGY_TRY;

                if ($strategy === static::STRATEGY_FAIL) {
                    if ($this->isDebug()) {
                        throw MoveFileException::fromAndToAreTheSame($from, $to);
                    }

                    return false;
                }

                if ($strategy === static::STRATEGY_IGNORE) {
                    return false;
                }
            }

            $this->adapter->move($from, $to, $config);

            return true;
        } catch (MoveFileException $e) {
            if ($this->debug) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function copy(string $from, string $to, array $config = []): bool
    {
        try {
            $config = $this->combiningMoveAndCopyOptions($config);
            $from = Path::normalizePath($from);
            $to = Path::normalizePath($to);

            if ($from === $to) {
                $strategy = $config[Filesystem::OPTION_COPY_IDENTICAL_PATH] ?? static::STRATEGY_TRY;

                if ($strategy === static::STRATEGY_FAIL) {
                    if ($this->isDebug()) {
                        throw CopyFileException::fromAndToAreTheSame($from, $to);
                    }

                    return false;
                }

                if ($strategy === static::STRATEGY_IGNORE) {
                    return false;
                }
            }

            $systemType = $this->getConfig('systemType');

            if ($systemType !== null) {
                $config['systemType'] = $systemType;
            }

            $this->adapter->copy($from, $to, $config);

            return true;
        } catch (CopyFileException $e) {
            if ($this->debug) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * Merge move/copy configuration with root options.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function combiningMoveAndCopyOptions(array $config): array
    {
        $retainVisibility = $config[Filesystem::OPTION_RETAIN_VISIBILITY]
            ?? ($config[Filesystem::OPTION_RETAIN_VISIBILITY] ?? true);

        $fullConfig = $this->combiningOptions($config);

        if (
            $retainVisibility &&
            ! array_key_exists(Filesystem::OPTION_VISIBILITY, $config)
        ) {
            $fullConfig = $this->excludeOptions(Filesystem::OPTION_VISIBILITY);
        }

        return $fullConfig;
    }
}
