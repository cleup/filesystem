<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Interfaces\SftpConnectivityCheckerInterface;
use phpseclib3\Net\SFTP;
use Throwable;

/**
 * Checks SFTP connection liveness.
 * Used by the file upload library to verify connections are still alive before file operations.
 * Supports simple connectivity check or optional ping-based verification.
 */
class SftpConnectivityChecker implements SftpConnectivityCheckerInterface
{
    /**
     * @param bool $usePing Whether to use ping for connectivity check (more thorough but slower).
     */
    public function __construct(
        private readonly bool $usePing = false,
    ) {}

    /**
     * Create a new instance with default settings (no ping).
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a copy with the specified ping setting.
     *
     * @param bool $usePing Whether to use ping for connectivity check.
     * @return self
     */
    public function withUsingPing(bool $usePing): self
    {
        return new self(usePing: $usePing);
    }

    /**
     * Check if the SFTP connection is still alive.
     *
     * @param SFTP $connection The SFTP connection to check.
     * @return bool True if connected (and ping succeeds, if enabled).
     */
    public function isConnected(SFTP $connection): bool
    {
        if (! $connection->isConnected()) {
            return false;
        }

        if (! $this->usePing) {
            return true;
        }

        try {
            return $connection->ping();
        } catch (Throwable) {
            return false;
        }
    }
}
