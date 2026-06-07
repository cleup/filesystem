<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Ftp;

use const FTP_BINARY;

/**
 * Immutable configuration value object for FTP/SFTP connections.
 * Used by the file upload library to establish and configure FTP adapter behavior.
 */
final readonly class FtpConnectionOptions
{
    /**
     * @param string $host FTP server hostname or IP address.
     * @param string $root Root directory path on the server.
     * @param string $username Authentication username.
     * @param string $password Authentication password.
     * @param int $port Connection port (default: 21 for FTP, 22 for SFTP).
     * @param bool $ssl Whether to use FTPS (SSL/TLS) encryption.
     * @param int $timeout Connection timeout in seconds.
     * @param bool $utf8 Enable UTF-8 mode on the server.
     * @param bool $passive Use passive transfer mode.
     * @param int $transferMode FTP transfer mode (FTP_BINARY or FTP_ASCII).
     * @param string|null $systemType Force server system type ("windows" or "unix"), or null to auto-detect.
     * @param bool|null $ignorePassiveAddress Whether to ignore the passive IP address returned by the server.
     * @param bool $enableTimestampsOnUnixListings Parse timestamps from Unix-style directory listings.
     * @param bool $recurseManually Manually recurse into subdirectories instead of using FTP -R flag.
     * @param bool|null $useRawListOptions Force use of raw list options, or null to auto-detect.
     */
    public function __construct(
        private string $host,
        private string $root,
        private string $username,
        private string $password,
        private int $port = 21,
        private bool $ssl = false,
        private int $timeout = 90,
        private bool $utf8 = false,
        private bool $passive = true,
        private int $transferMode = FTP_BINARY,
        private ?string $systemType = null,
        private ?bool $ignorePassiveAddress = null,
        private bool $enableTimestampsOnUnixListings = false,
        private bool $recurseManually = false,
        private ?bool $useRawListOptions = null,
    ) {}

    /**
     * Get the FTP server hostname.
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * Get the root directory path on the server.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * Get the authentication username.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Get the authentication password.
     */
    public function password(): string
    {
        return $this->password;
    }

    /**
     * Get the connection port.
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Check if SSL/TLS encryption is enabled.
     */
    public function ssl(): bool
    {
        return $this->ssl;
    }

    /**
     * Get the connection timeout in seconds.
     */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * Check if UTF-8 mode is enabled.
     */
    public function utf8(): bool
    {
        return $this->utf8;
    }

    /**
     * Check if passive transfer mode is used.
     */
    public function passive(): bool
    {
        return $this->passive;
    }

    /**
     * Get the FTP transfer mode (FTP_BINARY or FTP_ASCII).
     */
    public function transferMode(): int
    {
        return $this->transferMode;
    }

    /**
     * Get the forced server system type, or null to auto-detect.
     */
    public function systemType(): ?string
    {
        return $this->systemType;
    }

    /**
     * Check whether to ignore passive IP address returned by the server.
     */
    public function ignorePassiveAddress(): ?bool
    {
        return $this->ignorePassiveAddress;
    }

    /**
     * Check if timestamps should be parsed from Unix-style directory listings.
     */
    public function timestampsOnUnixListingsEnabled(): bool
    {
        return $this->enableTimestampsOnUnixListings;
    }

    /**
     * Check if recursion should be performed manually instead of via FTP -R flag.
     */
    public function recurseManually(): bool
    {
        return $this->recurseManually;
    }

    /**
     * Check if raw list options should be forced, or null to auto-detect.
     */
    public function useRawListOptions(): ?bool
    {
        return $this->useRawListOptions;
    }

    /**
     * Create an FtpConnectionOptions instance from a configuration array.
     *
     * @param array<string, mixed> $options Configuration key-value pairs.
     * @return static New instance with values from the array.
     */
    public static function fromArray(array $options): static
    {
        return new self(
            host: $options['host'] ?? 'invalid://host-not-set',
            root: $options['root'] ?? '',
            username: $options['username'] ?? 'invalid://username-not-set',
            password: $options['password'] ?? 'invalid://password-not-set',
            port: $options['port'] ?? 21,
            ssl: $options['ssl'] ?? false,
            timeout: $options['timeout'] ?? 90,
            utf8: $options['utf8'] ?? false,
            passive: $options['passive'] ?? true,
            transferMode: $options['transferMode'] ?? FTP_BINARY,
            systemType: $options['systemType'] ?? null,
            ignorePassiveAddress: $options['ignorePassiveAddress'] ?? null,
            enableTimestampsOnUnixListings: $options['timestampsOnUnixListingsEnabled'] ?? false,
            recurseManually: $options['recurseManually'] ?? true,
            useRawListOptions: $options['useRawListOptions'] ?? null,
        );
    }
}