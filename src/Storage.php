<?php

namespace Cleup\Filesystem;

use Cleup\Filesystem\Exceptions\DriverMethodException;
use Cleup\Filesystem\Exceptions\ReadFileException;
use Cleup\Filesystem\Interfaces\DriverInterface;

class Storage
{
    /**
     * @var array<string, mixed> $config
     */
    private static array $config = [];

    /**
     * @var bool
     */
    private static bool $debug = false;

    /**
     * @var Filesystem|null
     */
    private static ?Filesystem $filesystem = null;

    /**
     * @var array<string, DriverInterface> Кэш драйверов
     */
    private static array $drivers = [];

    /**
     * Add the file system configuration
     * 
     * @param array<string, mixed> $config
     * @return void
     */
    public static function configure(array $config = []): void
    {
        if (!empty($config['debug'])) {
            static::$debug = true;
            unset($config['debug']);
        }

        static::$config = $config;

        // Сбрасываем кэш при изменении конфигурации
        static::$filesystem = null;
        static::$drivers = [];
    }

    /**
     * Get the global storage configuration
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return static::$config ?: $default;
        }

        return static::$config[$key] ?? $default;
    }

    /**
     * Get or create a Filesystem instance
     * 
     * @return Filesystem
     */
    protected static function getFilesystem(): Filesystem
    {
        if (static::$filesystem === null) {
            static::$filesystem = new Filesystem(
                static::$config,
                static::$debug
            );
        }

        return static::$filesystem;
    }

    /**
     * Get a driver instance by name (cached)
     * 
     * @param string $name
     * @param array<string, mixed> $config
     * @return DriverInterface|null
     */
    public static function driver(string $name = Filesystem::DISK_LOCAL, array $config = []): ?DriverInterface
    {
        $cacheKey = $name . ':' . md5(serialize($config));

        if (!isset(static::$drivers[$cacheKey])) {
            static::$drivers[$cacheKey] = static::getFilesystem()->manager($name, $config);
        }

        return static::$drivers[$cacheKey];
    }

    /**
     * Create a new driver instance (alias for manager)
     * 
     * @param string $name
     * @param array<string, mixed> $config
     * @return DriverInterface|null
     */
    public static function manager(string $name, array $config = []): ?DriverInterface
    {
        // Не кэшируем, всегда создаем новый экземпляр через manager
        return static::getFilesystem()->manager($name, $config);
    }

    /**
     * Get the default driver instance
     * 
     * @return DriverInterface
     */
    protected static function getDefaultDriver(): DriverInterface
    {
        return static::driver(
            static::getConfig('driver', Filesystem::DISK_LOCAL),
            static::$config
        );
    }

    /**
     * Sanitize filename
     * 
     * @param string $filename
     * @return string
     */
    public static function sanitizeName(string $filename): string
    {
        $chars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|', '+', ' ', '%', '!', '@', '&', '$', '#', '`', ';', '(', ')', chr(0)];
        $filename = preg_replace("#\x{00a0}#siu", ' ', $filename);

        return str_replace($chars, '_', $filename);
    }

    /**
     * Sanitize file variables
     * 
     * @param array<string, mixed> $files - $_FILES["file"]
     * @return array<int, array<string, mixed>>
     */
    public static function sanitizeFileVariables(array $files): array
    {
        $result = [];
        $files = static::formatFileVariables($files);

        foreach ($files as $key => $value) {
            $result[$key] = [
                'name' => static::sanitizeName(
                    static::toString($value['name'])
                ),
                "full_path" => static::toString($value['full_path']),
                "tmp_name" => static::toString($value['tmp_name']),
                "size" => (int) $value['size'],
                "error" => (int) $value['error'],
                "type" => static::toString($value['type'])
            ];
        }

        return $result;
    }

    /**
     * Convert string to array
     * 
     * @param string $string
     * @return array<int, string>
     */
    private static function toList(string $string): array
    {
        $trimExt = trim($string);
        $replaceExt = str_replace(' ', '', $trimExt);

        return explode(',', $replaceExt);
    }

    /**
     * Check if the file extension exists in the array by name
     * 
     * @param string $fileName
     * @param string|array<int, string> $extension
     * @return bool
     */
    public static function isExtension(string $fileName, string|array $extension): bool
    {
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        if (is_string($extension)) {
            $list = static::toList($extension);

            if (count($list) > 1) {
                $extension = $list;
            }
        }

        return is_array($extension)
            ? in_array($fileExtension, $extension)
            : $fileExtension === $extension;
    }

    /**
     * Check the mime content type
     * 
     * @param string|array<int, string> $mimeType
     * @param string $filePath
     * @return bool
     */
    public static function isMimeType(string|array $mimeType, string $filePath): bool
    {
        $mime = mime_content_type($filePath);

        return is_array($mimeType)
            ? in_array($mime, $mimeType)
            : ($mimeType === $mime);
    }

    /**
     * Format to string
     * 
     * @param mixed $string
     * @return string
     */
    public static function toString(mixed $string): string
    {
        $string = strval($string);
        $string = preg_replace(
            '/[^\p{L}\p{N}\p{P}\s]+/u',
            '',
            $string
        );

        return str_replace(
            ["\r\n", "\r", "\n"],
            '',
            strip_tags(
                nl2br($string)
            )
        );
    }

    /**
     * Generate path with placeholders
     * 
     * @param string $path
     * @param string|null $filePath
     * @return string
     */
    public static function generatePath(string $path, ?string $filePath = null): string
    {
        $path = str_replace(
            [
                '{timestamp}',
                '{timestamp:md5}',
                '{uniquid}',
                '{uniquid:md5}',
                '{year}',
                '{year:md5}',
                '{month}',
                '{month:md5}',
                '{day}',
                '{day:md5}',
                '{rand}'
            ],
            [
                time(),
                md5((string) time()),
                uniqid(),
                md5(uniqid()),
                date('Y'),
                md5(date('Y')),
                date('m'),
                md5(date('m')),
                date('d'),
                md5(date('d')),
                rand()
            ],
            $path
        );

        if ($filePath !== null) {
            $path = str_replace(
                [
                    '{name}',
                    '{name:uniqueHashedName}',
                    '{extension}',
                    '{basename}',
                    '{dirname}'
                ],
                [
                    static::name($filePath, false),
                    static::uniqueHashedName($filePath, false),
                    static::extension($filePath, false),
                    static::basename($filePath, false),
                    static::dirname($filePath, false),
                ],
                $path
            );
        }

        return $path;
    }

    /**
     * Whether the array is HTTP file upload variables
     * 
     * @param array<string, mixed> $file
     * @param bool $isMultiple
     * @return bool
     */
    public static function isFileVariables(array $file = [], bool $isMultiple = false): bool
    {
        $isFile = !empty($file) &&
            is_array($file) &&
            !empty($file['name']) &&
            !empty($file['tmp_name']) &&
            !empty($file['size']) &&
            !empty($file['full_path']) &&
            isset($file['error']) &&
            !empty($file['type']);

        if ($isMultiple) {
            $isFile = $isFile && is_array($file['tmp_name']);
        }

        return $isFile;
    }

    /**
     * Format file variables array
     * 
     * @param array<string, mixed> $files
     * @return array<int, array<string, mixed>>
     */
    public static function formatFileVariables(array $files = []): array
    {
        $fileList = [];

        if (static::isFileVariables($files)) {
            $isMultiple = static::isFileVariables($files, true);

            if ($isMultiple) {
                $count = count($files['tmp_name']);
                $keys = array_keys($files);

                for ($i = 0; $i < $count; $i++) {
                    $fileList[$i] = [];

                    foreach ($keys as $key) {
                        $fileList[$i][$key] = $files[$key][$i];
                    }
                }
            } else {
                $fileList[] = $files;
            }
        }

        return $fileList;
    }

    /**
     * Add a response error
     * 
     * @param array<int, array<string, mixed>> $errors
     * @param string|int $code
     * @param int|null $key
     * @param string|null $name
     * @return void
     */
    private static function addResponseError(array &$errors, string|int $code, ?int $key = null, ?string $name = null): void
    {
        $error = ['code' => $code];

        if ($key !== null) {
            $error['key'] = $key;
        }

        if ($name !== null) {
            $error['name'] = $name;
        }

        $errors[] = $error;
    }

    /**
     * Prepare, filter and validate the file before uploading
     * 
     * @param array<string, mixed> $files
     * @param array<string, mixed> $params
     * @param bool $isPrepared
     * @return array<string, mixed>
     */
    public static function prepareUpload(
        array $files = [],
        array $params = [],
        bool $isPrepared = false,
        ?callable $onProcess = null
    ): array {
        $errors = [];
        $allFiles = [];
        $toUpload = [];

        $params = array_merge([
            'maxSize' => static::getConfig('maxSize', 15000000),
            'minSize' => static::getConfig('minSize', 0),
            'mimeType' => [],
            'extension' => [],
            'multiple' => false,
            'limit' => static::getConfig('limit', 5),
            'allowUpload' => static::getConfig('allowUpload', true),
            'calculateRealSize' => true
        ], $params);

        if (is_array($files) && !empty($files)) {
            if (static::isFileVariables($files) || $isPrepared) {
                $allFiles = $isPrepared && array_is_list($files)
                    ? $files
                    : static::sanitizeFileVariables($files);

                if (!empty($allFiles)) {
                    foreach ($allFiles as $key => $file) {
                        if (!file_exists($file['tmp_name'])) {
                            static::addResponseError($errors, 'file_not_found', $key, $file['name']);
                        } else {
                            $file['size'] = $params['calculateRealSize']
                                ? static::getRealSize($file['tmp_name'])
                                : $file['size'];

                            $file['originalKey'] = $key;

                            if (!$params['allowUpload']) {
                                static::addResponseError($errors, 'upload_is_not_available', $key, $file['name']);
                            } elseif (empty($params['multiple']) && $key > 0) {
                                static::addResponseError($errors, 'multiple_mode_is_not_available', $key, $file['name']);
                            } elseif ($params['multiple'] && ($key + 1) > intval($params['limit'])) {
                                static::addResponseError($errors, 'multiple_file_upload_limit', $key, $file['name']);
                            } elseif (!empty($params['extension']) && !static::isExtension($file['name'], $params['extension'])) {
                                static::addResponseError($errors, 'incorrect_extension', $key, $file['name']);
                            } elseif (!empty($params['mimeType']) && !static::isMimeType($params['mimeType'], $file['tmp_name'])) {
                                static::addResponseError($errors, 'incorrect_mime_type', $key, $file['name']);
                            } elseif ($file['size'] > $params['maxSize']) {
                                static::addResponseError($errors, 'large_file_size', $key, $file['name']);
                            } elseif (!$file['size'] || ($params['minSize'] && $file['size'] < $params['minSize'])) {
                                static::addResponseError($errors, 'small_file_size', $key, $file['name']);
                            } else {
                                $toUpload[] = $file;
                            }
                        }

                        if ($onProcess && is_callable($onProcess)) {
                            $onProcess(
                                $file,
                                $params,
                                $key,
                                $errors,
                                $toUpload,
                                $allFiles,
                                $isPrepared
                            );
                        }
                    }

                    return [
                        'status' => empty($errors),
                        'errors' => $errors,
                        'files' => $allFiles,
                        'prepared' => $toUpload
                    ];
                }
            } else {
                return static::prepareUpload($files, $params, true, $onProcess);
            }
        }

        return [];
    }

    /**
     * Upload files
     * 
     * @param array<string, mixed> $files
     * @param array<string, mixed> $params
     * @param DriverInterface|null $driver
     * @return array<string, mixed>
     */
    public static function upload(array $files = [], array $params = [], ?DriverInterface $driver = null): array
    {
        $prepareResponse = static::prepareUpload($files, $params);
        $prepareResponse['uploaded'] = [];

        $isDriver = $driver instanceof DriverInterface;
        $config = $isDriver ? $driver->getConfig() : static::getConfig();
        $path = $params['path'] ?? '';

        if (!empty($path)) {
            $path = static::generatePath($path);
            $path = !empty($path) ? rtrim($path, '/') . '/' : '';
            $path = ($config['root'] ?? false) ? ltrim($path, '/') : $path;
        }

        if ($prepareResponse['status'] || ($params['ignoreErrors'] ?? false)) {
            foreach ($prepareResponse['prepared'] as $key => $file) {
                $contents = @file_get_contents($file['tmp_name']);
                $name = $file['name'];

                if (!empty($params['encryptName'])) {
                    $name = is_string($params['encryptName'])
                        ? static::generatePath($params['encryptName'], $name)
                        : static::uniqueHashedName($name);
                }

                if ($contents !== false) {
                    if ($isDriver) {
                        $driver->put($path . $name, $contents);
                    } else {
                        static::put($path . $name, $contents);
                    }

                    $prepareResponse['uploaded'][$key] = [
                        'name' => $name,
                        'realName' => $file['name'],
                        'path' => $path,
                        'fullPath' => $path . $name,
                        'absolutePath' => $isDriver
                            ? $driver->path($path . $name)
                            : static::path($path . $name),
                        'mimeType' => $file['type'],
                        'size' => $file['size']
                    ];
                } else {
                    if (static::$debug) {
                        throw ReadFileException::fromLocation($file['tmp_name']);
                    }
                }
            }
        }

        return $prepareResponse;
    }

    /**
     * Get the real file size
     * 
     * @param string $path
     * @return int
     */
    public static function getRealSize(string $path): int
    {
        $stream = fopen($path, 'r+');

        if ($stream === false) {
            return 0;
        }

        $stat = fstat($stream);
        fclose($stream);

        return $stat['size'] ?? 0;
    }

    /**
     * Magic method to delegate calls to driver or filesystem
     * 
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $driverClass = Driver::class;
        $filesystemClass = Filesystem::class;

        // Сначала проверяем методы Driver (базового класса)
        if (method_exists($driverClass, $method)) {
            $driver = static::getDefaultDriver();
            return $driver->{$method}(...$arguments);
        }

        // Затем проверяем методы Filesystem
        if (method_exists($filesystemClass, $method)) {
            $filesystem = static::getFilesystem();
            return $filesystem->{$method}(...$arguments);
        }

        // Пробуем найти метод в интерфейсе драйвера
        if (interface_exists(DriverInterface::class) && method_exists(DriverInterface::class, $method)) {
            $driver = static::getDefaultDriver();
            return $driver->{$method}(...$arguments);
        }

        if (static::$debug) {
            throw new DriverMethodException(
                sprintf(
                    'Method %s::%s() not found in Driver, Filesystem or DriverInterface',
                    static::class,
                    $method
                )
            );
        }

        return null;
    }

    /**
     * Helper methods for path operations that delegate to driver
     */

    public static function name(string $path, bool $pathPrefix = true): string
    {
        return static::getDefaultDriver()->name($path, $pathPrefix);
    }

    public static function basename(string $path, bool $pathPrefix = true): string
    {
        return static::getDefaultDriver()->basename($path, $pathPrefix);
    }

    public static function dirname(string $path, bool $pathPrefix = true): string
    {
        return static::getDefaultDriver()->dirname($path, $pathPrefix);
    }

    public static function extension(string $path, bool $pathPrefix = true): string
    {
        return static::getDefaultDriver()->extension($path, $pathPrefix);
    }

    public static function uniqueHashedName(string $path, bool $isExtension = true): string
    {
        return static::getDefaultDriver()->uniqueHashedName($path, $isExtension);
    }
}
