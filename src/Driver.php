<?php

namespace Cleup\Filesystem;

use Generator;
use Throwable;
use Cleup\Filesystem\Support\VisibilityConverter;
use Cleup\Filesystem\Adapters\ReadOnly\ReadOnlyAdapter;
use Cleup\Filesystem\Exceptions\InvalidChecksumAlgoException;
use Cleup\Filesystem\Exceptions\CopyFileException;
use Cleup\Filesystem\Exceptions\CreateDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteDirectoryException;
use Cleup\Filesystem\Exceptions\DeleteFileException;
use Cleup\Filesystem\Exceptions\InvalidStreamProvidedException;
use Cleup\Filesystem\Exceptions\FinderException;
use Cleup\Filesystem\Exceptions\MoveFileException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Exceptions\RetrieveMetadataException;
use Cleup\Filesystem\Exceptions\SetVisibilityException;
use Cleup\Filesystem\Exceptions\WriteFileException;
use Cleup\Filesystem\Filesystem;
use Cleup\Filesystem\Finder\Finder;
use Cleup\Filesystem\Support\PathNormalizer;
use Cleup\Filesystem\Interfaces\DriverInterface;
use Cleup\Filesystem\Interfaces\PathNormalizerInterface;
use Cleup\Filesystem\Support\PathPrefixer;
use Cleup\Filesystem\Interfaces\AdapterInterface;
use Cleup\Filesystem\Interfaces\FinderAttributesInterface;
use Cleup\Filesystem\Interfaces\FinderFileAttributesInterface;
use Psr\Http\Message\StreamInterface;

use function array_key_exists;

class Driver implements DriverInterface
{
    /**
     * @var string
     */
    public const STRATEGY_IGNORE = 'ignore';

    /**
     * @var string
     */
    public const STRATEGY_FAIL = 'fail';

    /**
     * @var string
     */
    public const STRATEGY_TRY = 'try';

    /**
     * @var PathNormalizerInterface
     */
    private $pathNormalizer;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected $prefixer;

    /**
     * Driver Configuration
     *
     * @var array
     */
    private $config;

    /**
     * Create driver
     * 
     * @param array $config
     * @param bool $debug
     */
    public function __construct(
        $config = [],
        protected $debug = false
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

        $defaultConfig = call_user_func([$this, 'configure']) ?? array();

        if ($defaultConfig)
            $config = array_merge($defaultConfig, $config);

        $this->config = $config;

        $this->mountDriver();
    }

    /**
     * Get configuration by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($key = null, $default = null)
    {
        if (is_null($key))
            return $this->config;

        return $this->config[$key] ?? $default;
    }

    /**
     * @return bool
     */
    protected function isDebug()
    {
        return !!$this->debug;
    }

    /**
     * Use a visibility converter
     * 
     * @param VisibilityConverter|null $сonverter
     * @return VisibilityConverter
     */
    protected function visibilityConverter($сonverter = null)
    {
        return $сonverter ?? VisibilityConverter::fromArray(
            $this->getConfig('permissions', []),
            $this->getConfig(Filesystem::OPTION_DIRECTORY_VISIBILITY) ??
                $this->getConfig(Filesystem::OPTION_VISIBILITY) ??
                Filesystem::VISIBILITY_PRIVATE
        );
    }

    /**
     * Use only selected elements of the array
     * 
     * @param array $arr
     * @param array $items
     * @return array
     */
    protected function onlyArrayItems($arr, $items = [])
    {
        return array_intersect_key(
            $arr,
            array_flip($items)
        );
    }

    /**
     * Get root options
     * 
     * @return array
     */
    private function getRootOptions()
    {
        return $this->onlyArrayItems(
            $this->config,
            [
                Filesystem::OPTION_COPY_IDENTICAL_PATH,
                Filesystem::OPTION_MOVE_IDENTICAL_PATH,
                Filesystem::OPTION_VISIBILITY,
                Filesystem::OPTION_DIRECTORY_VISIBILITY,
                Filesystem::OPTION_RETAIN_VISIBILITY
            ]
        );
    }

    /**
     * Exclude options from the root configuration array
     * 
     * @param string $options
     * @return array
     */
    public function excludeOptions(...$options)
    {
        return array_diff_key(
            $this->getRootOptions(),
            array_flip($options)
        );
    }

    /**
     * Combining options with the root configuration
     * 
     * @param array $options
     * @return array
     */
    public function сombiningOptions($options)
    {
        return array_merge(
            $this->getRootOptions(),
            $options
        );
    }

    /**
     * Mount the driver
     * 
     * @return void
     */
    private function mountDriver()
    {
        $adapter = call_user_func([$this, 'create']);

        if ($this->getConfig('readOnly')) {
            $adapter = new ReadOnlyAdapter($adapter);
        }

        $this->pathNormalizer = $this->getConfig('pathNormalizer', null)
            ?? new PathNormalizer();

        $this->adapter = $adapter;
    }

    /**
     * Get the full path to the file that exists at the given relative path.
     *
     * @param string $path
     * @param bool $normalize
     * @return string
     */
    public function path($path, $normalize = false)
    {
        if ($normalize) {
            return $this->normalizePath(
                $this->prefixer->prefixPath($path)
            );
        }

        return $this->prefixer->prefixPath($path);
    }

    /**
     * Normalize path
     * 
     * @param string $path
     * @return string
     */
    public function normalizePath($path)
    {
        return $this->pathNormalizer->normalizePath($path);
    }

    /**
     * Extract the file name from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function name($path, $pathPrefix = true)
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_FILENAME
        );
    }

    /**
     * Extract the trailing name component from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function basename($path, $pathPrefix = true)
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_BASENAME
        );
    }

    /**
     * Extract the parent directory from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function dirname($path, $pathPrefix = true)
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_DIRNAME
        );
    }

    /**
     * Extract the file extension from a file path.
     *
     * @param string $path
     * @param bool $pathPrefix
     * @return string
     */
    public function extension($path, $pathPrefix = true)
    {
        return pathinfo(
            $pathPrefix ? $this->path($path) : $path,
            PATHINFO_EXTENSION
        );
    }

    /**
     * Unique hashed name.
     *
     * @param string $path
     * @param bool $isExtension
     * @return string
     */
    public function uniqueHashedName($path, $isExtension = true)
    {
        $name = $this->name($path);
        $extension = $this->extension($path);

        return md5($name) . uniqid() . ($isExtension ? '.' . $extension : '');
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists($path)
    {
        $path = $this->pathNormalizer->normalizePath($path);

        return $this->adapter->fileExists($path) ||
            $this->adapter->directoryExists($path);
    }

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function fileExists($path)
    {
        return $this->adapter->fileExists(
            $this->pathNormalizer->normalizePath($path)
        );
    }

    /**
     * Determine if a directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function directoryExists($path)
    {
        return $this->adapter->directoryExists(
            $this->pathNormalizer->normalizePath($path)
        );
    }

    /**
     * Get the contents of a file.
     *
     * @param string  $path
     * @return string|null
     */
    public function get($path)
    {
        try {
            return $this->adapter->get(
                $this->pathNormalizer->normalizePath($path)
            );
        } catch (ReadFileException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return null;
    }

    /**
     * Get a resource to read the file.
     *
     * @param string $path
     * @return resource|null The path resource or null on failure.
     */
    public function readStream($path)
    {
        try {
            return $this->adapter->readStream(
                $this->pathNormalizer->normalizePath($path)
            );
        } catch (ReadFileException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return null;
    }

    /**
     * Get the contents of a file as decoded JSON.
     *
     * @param string $path
     * @param int $flags
     * @return array|null
     */
    public function json($path, $flags = 0)
    {
        $content = $this->get($path);

        return is_null($content)
            ? null
            : json_decode($content, true, 512, $flags);
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     */
    public function lastModified($path)
    {
        return $this->adapter->lastModified(
            $this->pathNormalizer->normalizePath($path)
        )->lastModified();
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     */
    public function size($path)
    {
        return $this->adapter->size(
            $this->pathNormalizer->normalizePath($path)
        )->size();
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false
     */
    public function mimeType($path)
    {
        try {
            return $this->adapter->mimeType(
                $this->pathNormalizer->normalizePath($path)
            )->mimeType();
        } catch (RetrieveMetadataException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Get the checksum for a file.
     *
     * @param string $path
     * @param array $config
     * @return string|bool
     */
    public function checksum($path, $config = [])
    {
        $config = $this->сombiningOptions($config);

        try {
            $stream = $this->readStream($path);
            $algo = (string) ($config['checksum_algo'] ?? 'md5');
            $context = hash_init($algo);
            hash_update_stream($context, $stream);

            return hash_final($context);
        } catch (InvalidChecksumAlgoException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Finder
     * 
     * @param string $path
     * @param bool $deep
     * @return iterable<FinderAttributesInterface>
     */
    public function finder($path, $deep = false): Finder
    {
        $path = $this->pathNormalizer->normalizePath($path);
        $listing = $this->adapter->finder($path, $deep);

        return new Finder(
            $this->finderGenerator(
                $path,
                $deep,
                $listing
            )
        );
    }

    /**
     * Finder option generator
     * 
     * @param string $path
     * @param bool $deep
     * @param iterable $listing
     * @return Generator
     */
    private function finderGenerator($path, $deep, $listing)
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
     * Get an array of all files in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function files($directory = null, $recursive = false)
    {
        return $this->finder($directory ?? '', $recursive)
            ->filter(function (FinderFileAttributesInterface $attributes) {
                return $attributes->isFile();
            })
            ->sortByPath()
            ->map(function (FinderFileAttributesInterface $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function directories($directory = null, $recursive = false)
    {
        return $this->finder($directory ?? '', $recursive)
            ->filter(function (FinderAttributesInterface $attributes) {
                return $attributes->isDir();
            })
            ->map(function (FinderAttributesInterface $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * Get the entire contents of a directory as a map.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function fileMap($directory = null, $recursive = false)
    {
        return $this->finder($directory ?? '')
            ->map(function (FinderAttributesInterface|FinderFileAttributesInterface $attributes) use ($recursive) {
                $item = [
                    'name' => $this->name($attributes->path()),
                    'type' => $attributes->isDir() ? 'directory' : 'file',
                    'path' => $attributes->path(),
                    'fullPath' => $this->path(
                        $attributes->path(),

                    ),
                    'lastModified' => $attributes->lastModified()
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
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string|null
     */
    public function getVisibility($path)
    {
        try {
            return $this->adapter->getVisibility(
                $this->pathNormalizer->normalizePath($path)
            )->getVisibility();
        } catch (RetrieveMetadataException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return Filesystem::VISIBILITY_PRIVATE;
    }

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     */
    public function setVisibility($path, $visibility)
    {
        try {
            $this->adapter->setVisibility(
                $this->pathNormalizer->normalizePath($path),
                $visibility
            );

            return true;
        } catch (SetVisibilityException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|string|resource $contents
     * @param mixed $config
     * @return bool
     */
    public function put($path, $contents, $config = [])
    {

        $config = is_string($config)
            ? ['visibility' => $config]
            : (array) $config;

        $config = $this->сombiningOptions($config);

        try {
            if ($contents instanceof StreamInterface) {
                $this->adapter->writeStream($path, $contents->detach(), $config);

                return true;
            }

            if (is_resource($contents)) {
                $this->adapter->writeStream($path, $contents, $config);
            } else {
                $this->adapter->put(
                    $this->pathNormalizer->normalizePath($path),
                    $contents,
                    $config
                );
            }

            return true;
        } catch (WriteFileException | SetVisibilityException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|string|resource $contents
     * @param array $options
     * @return bool
     */
    public function writeStream($path, $contents, $config = [])
    {
        if ($this->isDebug()) {
            if (is_resource($contents) === false) {
                throw new InvalidStreamProvidedException(
                    "Invalid stream provided, expected stream resource, received " . gettype($contents)
                );
            } elseif ($type = get_resource_type($contents) !== 'stream') {
                throw new InvalidStreamProvidedException(
                    "Invalid stream provided, expected stream resource, received resource of type " . $type
                );
            }
        }

        if (ftell($contents) !== 0 && stream_get_meta_data($contents)['seekable']) {
            rewind($contents);
        }

        try {
            $this->adapter->writeStream(
                $this->pathNormalizer->normalizePath($path),
                $contents,
                $this->сombiningOptions($config)
            );

            return true;
        } catch (WriteFileException | SetVisibilityException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array $config
     * @return bool
     */
    public function prepend($path, $data, $separator = PHP_EOL, $config = [])
    {
        if ($this->fileExists($path)) {
            return $this->put(
                $path,
                $data . $separator . $this->get($path),
                $config
            );
        }

        return $this->put(
            $path,
            $data,
            $config
        );
    }

    /**
     * Append to a file.
     * 
     * @param string $path
     * @param string $data
     * @param string $separator
     * @param array $config
     * @return bool
     */
    public function append($path, $data, $separator = PHP_EOL, $config = [])
    {
        if ($this->fileExists($path)) {
            return $this->put(
                $path,
                $this->get($path) . $separator . $data,
                $config
            );
        }

        return $this->put(
            $path,
            $data,
            $config
        );
    }

    /**
     * Replace a given string within a given file.
     *
     * @param array|string $search
     * @param array|string $replace
     * @param string $path
     * @param array $config
     * @return bool
     */
    public function replaceInFile($search, $replace, $path, $config = [])
    {
        return $this->put(
            $path,
            str_replace(
                $search,
                $replace,
                $this->get($path)
            ),
            $config
        );
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function createDirectory($path, $config = [])
    {
        try {
            $this->adapter->createDirectory(
                $this->pathNormalizer->normalizePath($path),
                $this->сombiningOptions($config)
            );

            return true;
        } catch (CreateDirectoryException | SetVisibilityException $exception) {
            if ($this->isDebug())
                throw $exception;
        }

        return false;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory($directory)
    {
        try {
            $this->adapter->deleteDirectory(
                $this->pathNormalizer->normalizePath($directory)
            );
        } catch (DeleteDirectoryException $e) {
            if ($this->debug)
                throw $e;

            return false;
        }

        return true;
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|array $path
     * @return bool
     */
    public function delete($path)
    {
        $path = is_array($path)
            ? $path
            : func_get_args();

        $success = true;

        foreach ($path as $item) {
            try {
                $this->adapter->delete(
                    $this->pathNormalizer->normalizePath($item)
                );
            } catch (DeleteFileException $exception) {
                if ($this->isDebug())
                    throw $exception;

                $success = false;
            }
        }

        return $success;
    }

    /**
     * Move a file to a new path.
     *
     * @param string $from
     * @param string $to
     * @param array $config
     * @return bool
     */
    public function move($from, $to, $config = [])
    {
        try {
            $config = $this->combiningMoveAndCopyOptions($config);
            $from = $this->pathNormalizer->normalizePath($from);
            $to = $this->pathNormalizer->normalizePath($to);

            if ($from === $to) {
                $strategy = $config[Filesystem::OPTION_MOVE_IDENTICAL_PATH] ?? static::STRATEGY_TRY;

                if ($strategy === static::STRATEGY_FAIL) {
                    if ($this->isDebug()) {
                        throw MoveFileException::fromAndToAreTheSame($from, $to);
                    } else {
                        return false;
                    }
                } elseif ($strategy === static::STRATEGY_IGNORE) {
                    return false;
                }
            }

            $this->adapter->move($from, $to, $config);

            return true;
        } catch (MoveFileException $e) {
            if ($this->debug)
                throw $e;
        }

        return false;
    }

    /**
     * Copy a file to a new path.
     *
     * @param string  $from
     * @param string  $to
     * @param array $config
     * @return bool
     */
    public function copy($from, $to, $config = [])
    {
        try {
            $config = $this->combiningMoveAndCopyOptions($config);
            $from = $this->pathNormalizer->normalizePath($from);
            $to = $this->pathNormalizer->normalizePath($to);

            if ($from === $to) {
                $strategy = $config[Filesystem::OPTION_COPY_IDENTICAL_PATH] ?? static::STRATEGY_TRY;

                if ($strategy === static::STRATEGY_FAIL) {
                    if ($this->isDebug()) {
                        throw CopyFileException::fromAndToAreTheSame($from, $to);
                    } else {
                        return false;
                    }
                } elseif ($strategy === static::STRATEGY_IGNORE) {
                    return false;
                }
            }

            $systemType = $this->getConfig('systemType', null);

            if ($systemType) {
                $config['systemType'] = $systemType;
            }

            $this->adapter->copy($from, $to, $config);

            return true;
        } catch (CopyFileException $e) {
            if ($this->debug)
                throw $e;
        }

        return false;
    }

    /**
     * Combining move and copy configuration options
     * 
     * @param array $config
     * @return array
     */
    private function combiningMoveAndCopyOptions($config)
    {
        $retainVisibility = $config[Filesystem::OPTION_RETAIN_VISIBILITY]
            ?? ($config[Filesystem::OPTION_RETAIN_VISIBILITY] ?? true);

        $fullConfig = $this->сombiningOptions($config);

        if (
            $retainVisibility &&
            !array_key_exists(
                Filesystem::OPTION_VISIBILITY,
                $config
            )
        ) {
            $fullConfig = $this->excludeOptions(Filesystem::OPTION_VISIBILITY);
        }

        return $fullConfig;
    }
}
