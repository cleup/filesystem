<?php

namespace Cleup\Filesystem;

use Cleup\Filesystem\Exceptions\DriverMethodException;
use Cleup\Filesystem\Exceptions\ReadFileException;

class Storage
{
    /**
     * @var array $config
     */
    private static $config = [];

    /**
     * @var bool
     */
    private static $debug = false;

    /**
     * @var Filesystem
     */
    private static $filesystem = null;

    /**
     * Add the file system configuration
     * 
     * @param array $config
     * @return void
     */
    public static function configure($config = [])
    {
        if (!empty($config['debug'])) {
            static::$debug = true;
            unset($config['debug']);
        }

        static::$config = $config;
    }

    /**
     * Get the global storage configuration
     * @param string $key
     * @return mixed
     */
    public static function getConfig($key = null, $default = null)
    {
        return $key
            ? (static::$config[$key] ?? $default ?? null)
            : static::$config ?? $default ?? null;
    }

    /**
     * Sanitize filename
     * 
     * @param string $file
     * @return string
     */
    public static function sanitizeName($filename)
    {
        $chars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|', '+', ' ', '%', '!', '@', '&', '$', '#', '`', ';', '(', ')', chr(0)];
        $filename = preg_replace("#\x{00a0}#siu", ' ', $filename);

        return str_replace($chars, '_', $filename);
    }

    /**
     * Sanitize file variables
     * 
     * @param array $files - $_FILES["file"]
     * @return array
     */
    public static function sanitizeFileVariables($files)
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
                "size" => intval($value['size']),
                "error" => intval($value['error']),
                "type" => static::toString($value['type'])
            ];
        }

        return $result;
    }

    /**
     * @param string $string
     * @return array
     */
    private static function toList($string)
    {
        $trimExt = trim($string);
        $replaceExt = str_replace(' ', '', $trimExt);

        return explode(',',  $replaceExt);
    }

    /**
     * Check if the file extension exists in the array by name (Local driver only)
     * 
     * @param string $fileName
     * @param array  $extension
     * @return bool
     */
    public static function isExtension($fileName, $extension)
    {
        $fileExtension = pathinfo(
            $fileName,
            PATHINFO_EXTENSION
        );

        if (is_string($extension)) {
            $list = static::toList($extension);

            if (count($list) > 1)
                $extension = $list;
        }

        return is_array($extension)
            ? in_array($fileExtension, $extension)
            : $fileExtension === $extension;
    }

    /**
     * Check the mime content type
     * 
     * @param string $mimeType
     * @param string $filePath
     * @return bool
     */
    public static function isMimeType($mimeType, $filePath)
    {
        $mime = mime_content_type($filePath);

        return is_array($mimeType)
            ? in_array($mime, $mimeType)
            : ($mimeType === $mime);
    }

    /**
     * Format to string
     * 
     * @param string $string
     * @return string
     */
    public static function toString($string)
    {
        $string = strval($string);
        $string =  preg_replace(
            '/[^\p{L}\p{N}\p{P}\s]+/u',
            '',
            $string
        );

        return str_replace(
            array("\r\n", "\r", "\n"),
            '',
            strip_tags(
                nl2br($string)
            )
        );
    }

    /**
     * Generate a date path
     * 
     * @param string $prefix
     * @param bool $isYear
     * @param bool $isDay
     * @param bool $prefix
     * @return string
     */
    public static function generatePathByDate(
        $prefix = '',
        $isYear = true,
        $isMonth = true,
        $isDay = false
    ) {
        $year = $isYear ? '/' . md5(date('Y')) : '';
        $month = $isMonth ? '/' . md5(date('m')) : '';
        $day = $isDay ? '/' . md5(date('d')) : '';

        return trim($prefix, '/') .  $year . $month . $day;
    }

    /**
     * Generate path
     * 
     * @param string $path
     * @param string|null $filePath
     * @return string
     */
    public static function generatePath($path, $filePath = null)
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
                md5(time()),
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

        if ($filePath) {
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
     * @param array $file
     * @param bool $isMultiple
     * @return bool
     */
    public static function isFileVariables($file = [], $isMultiple = false)
    {
        $isFile = !empty($file) &&
            is_array($file) &&
            !empty($file['name']) &&
            !empty($file['tmp_name']) &&
            !empty($file['size']) &&
            !empty($file['full_path']) &&
            isset($file['error']) &&
            !empty($file['type']);

        if ($isMultiple)
            $isFile = $isFile && is_array($file['tmp_name']);


        return $isFile;
    }

    public static function formatFileVariables($files = [])
    {
        $fileList = [];

        if (static::isFileVariables($files)) {
            $isMultiple = static::isFileVariables($files, true);

            if ($isMultiple) {
                $count = count($files['tmp_name']);
                $keys  = array_keys($files);

                for ($i = 0; $i < $count; $i++) {
                    $fileList[$i] = [];

                    foreach ($keys as $key) {
                        $fileList[$i][$key] = $files[$key][$i];
                    }
                }
            } else
                $fileList[] = $files;
        }

        return $fileList;
    }

    /**
     * Add a response error
     * 
     * @param array $erros
     * @param string|int $code
     * @param int|null $key
     * @param string|null $name
     * @return void
     */
    private static function addResponseError(&$errors, $code, $key = null, $name = null)
    {
        $error =  [
            'code' => $code
        ];

        if (!is_null($key))
            $error['key'] = $key;

        if (!is_null($name))
            $error['name'] = $name;

        $errors[] = $error;
    }

    /**
     * Prepare, filter and validate the file before uploading
     * 
     * @param array $files - $_FILES['files'] or prepared data
     * @param array $params
     * @param bool $isPrpared
     * @return array
     */
    public static function prepareUpload($files = [], $params = [], $isPrpared = false)
    {
        $errors = [];
        $allFiles = [];
        $toUpload = [];
        $response = [];
        $params = array_merge([
            'maxSize' => static::getConfig('maxSize', 15000),          // Maximum file size (KB)
            'mimeType' => [],                                          // Valid mime type
            'extension' => [],                                         // Available file extensions
            'multiple' => false,                                       // Multiple file uploads
            'limit' => static::getConfig('limit', 5),                  // Maximum number of files to upload
            'allowUpload' => static::getConfig('allowUpload', true),   // Allow file upload
        ], $params);

        if (is_array($files) && !empty($files)) {
            if (static::isFileVariables($files) || $isPrpared) {
                $allFiles = $isPrpared && array_is_list($files)
                    ? $files
                    : static::sanitizeFileVariables($files);

                if (!empty($allFiles)) {
                    foreach ($allFiles as $key => $file) {
                        if (!file_exists($file['tmp_name'])) {
                            static::addResponseError($errors, 'file_not_found', $key, $file['name']);
                        } elseif (!$params['allowUpload']) {
                            static::addResponseError(
                                $errors,
                                'upload_is_not_available',
                                $key,
                                $file['name']
                            );
                        } elseif (empty($params['multiple']) && $key > 0) {
                            static::addResponseError(
                                $errors,
                                'multiple_mode_is_not_available',
                                $key,
                                $file['name']
                            );
                        } elseif ($params['multiple'] && ($key + 1) > intval($params['limit'])) {
                            static::addResponseError(
                                $errors,
                                'multiple_file_upload_limit',
                                $key,
                                $file['name']
                            );
                        } elseif (
                            !empty($params['extension']) &&
                            !static::isExtension($file['name'], $params['extension'])
                        ) {
                            static::addResponseError(
                                $errors,
                                'incorrect_extension',
                                $key,
                                $file['name']
                            );
                        } elseif (
                            !empty($params['mimeType']) &&
                            !static::isMimeType($params['mimeType'], $file['tmp_name'])
                        ) {
                            static::addResponseError(
                                $errors,
                                'incorrect_mime_type',
                                $key,
                                $file['name']
                            );
                        } elseif ($file['size'] > ($params['maxSize'] * 1000)) {
                            static::addResponseError(
                                $errors,
                                'large_file_size',
                                $key,
                                $file['name']
                            );
                        } else {
                            $toUpload[] = $file;
                        }
                    }

                    $response = [
                        'status' => empty($errors),
                        'errors' => $errors,
                        'files' => $allFiles,
                        'prepared' => $toUpload
                    ];
                }
            } else
                $response =  static::prepareUpload($files, $params, true);
        }

        return $response;
    }

    /**
     * Upload files
     * 
     * @param array $files
     * @param array $params
     * @param Driver|null $driver
     * @return array
     */
    public static function upload($files = [], $params = [], $driver = null)
    {
        $prepareResponse = static::prepareUpload($files, $params);
        $prepareResponse['uploaded'] = [];
        $isDriver = $driver instanceof Driver;
        $config = $isDriver
            ? $driver->getConfig()
            : static::getConfig();
        $path = $params['path'] ?? '';

        if (!empty($path)) {
            $path = static::generatePath($path);
            $path = !empty($path) ? rtrim($path, '/') . '/' : '';
            $path = ($config['root'] ?? false)
                ? ltrim($path, '/')
                : $path;
        }

        if ($prepareResponse['status'] || ($params['ingoreErrors'] ?? false)) {
            foreach ($prepareResponse['prepared'] as $key => $file) {
                $contents = @file_get_contents($file['tmp_name']);
                $name = $file['name'];

                if (!empty($params['encryptName'])) {
                    if (is_string($params['encryptName']))
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
                        'absolutePath' => (
                            $isDriver
                            ? $driver->path($path . $name)
                            : static::path($path . $name)
                        ),
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

    public static function __callStatic($method, $arguments)
    {
        $driverClass = "\\" . Driver::class;
        $filesystemClass = "\\" . Filesystem::class;

        if (
            method_exists($driverClass, $method) ||
            method_exists($filesystemClass, $method)
        ) {
            if (empty(static::$filesystem)) {
                static::$filesystem = new Filesystem(
                    static::$config,
                    static::$debug
                );
            }
            return call_user_func_array([
                static::$filesystem,
                $method
            ], [...$arguments]);
        } else {
            if (static::$debug) {
                throw new DriverMethodException(
                    $driverClass . '::' . $method . '() or ' . $filesystemClass . '::' . $method
                );
            }
        }
    }
}
