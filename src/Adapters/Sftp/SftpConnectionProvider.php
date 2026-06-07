<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Exceptions\SftpAuthenticateException;
use Cleup\Filesystem\Exceptions\SftpConnectToHostException;
use Cleup\Filesystem\Exceptions\SftpEstablishAuthenticityOfHostException;
use Cleup\Filesystem\Exceptions\SftpLoadPrivateKeyException;
use Cleup\Filesystem\Interfaces\FilesystemExceptionInterface;
use Cleup\Filesystem\Interfaces\SftpConnectionProviderInterface;
use Cleup\Filesystem\Interfaces\SftpConnectivityCheckerInterface;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use phpseclib3\System\SSH\Agent;
use Throwable;

use function base64_decode;
use function implode;
use function str_split;

/**
 * SFTP connection provider for the file upload library.
 * Manages SFTP connection lifecycle, authentication, and reconnection.
 */
class SftpConnectionProvider implements SftpConnectionProviderInterface
{
    private ?SFTP $connection = null;
    private SftpConnectivityCheckerInterface $connectivityChecker;

    /**
     * @param string $host SFTP hostname or IP address.
     * @param string $username Authentication username.
     * @param string|null $password Authentication password.
     * @param string|null $privateKey Path to private key file or key contents.
     * @param string|null $passphrase Passphrase for the private key.
     * @param int $port Connection port.
     * @param bool $useAgent Whether to use SSH agent for authentication.
     * @param int $timeout Connection timeout in seconds.
     * @param int $maxTries Maximum number of connection attempts.
     * @param string|null $hostFingerprint Expected host fingerprint for verification.
     * @param SftpConnectivityCheckerInterface|null $connectivityChecker Checks if the connection is still alive.
     * @param array<int, string> $preferredAlgorithms List of preferred encryption algorithms.
     * @param bool $disableStatCache Whether to disable the SFTP stat cache.
     */
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly ?string $password = null,
        private ?string $privateKey = null,
        private readonly ?string $passphrase = null,
        private readonly int $port = 22,
        private readonly bool $useAgent = false,
        private readonly int $timeout = 10,
        private readonly int $maxTries = 4,
        private readonly ?string $hostFingerprint = null,
        ?SftpConnectivityCheckerInterface $connectivityChecker = null,
        private readonly array $preferredAlgorithms = [],
        private readonly bool $disableStatCache = true,
    ) {
        $this->connectivityChecker = $connectivityChecker ?? new SftpConnectivityChecker();
    }

    /**
     * Provide an active SFTP connection, reconnecting if necessary.
     *
     * @return SFTP
     * @throws SftpConnectToHostException
     */
    public function provideConnection(): SFTP
    {
        $tries = 0;
        start:
        $tries++;

        try {
            $connection = $this->connection instanceof SFTP
                ? $this->connection
                : $this->setupConnection();
        } catch (Throwable $exception) {
            if ($tries <= $this->maxTries) {
                goto start;
            }

            if ($exception instanceof FilesystemExceptionInterface) {
                throw $exception;
            }

            throw SftpConnectToHostException::atHostname($this->host, $exception);
        }

        if (! $this->connectivityChecker->isConnected($connection)) {
            $connection->disconnect();
            $this->connection = null;

            if ($tries <= $this->maxTries) {
                goto start;
            }

            throw SftpConnectToHostException::atHostname($this->host);
        }

        return $this->connection = $connection;
    }

    /**
     * Disconnect the active SFTP connection.
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * Set up a new SFTP connection with authentication.
     *
     * @return SFTP
     * @throws SftpConnectToHostException
     * @throws SftpAuthenticateException
     * @throws SftpEstablishAuthenticityOfHostException
     */
    private function setupConnection(): SFTP
    {
        $connection = new SFTP($this->host, $this->port, $this->timeout);
        $connection->setPreferredAlgorithms($this->preferredAlgorithms);

        if ($this->disableStatCache) {
            $connection->disableStatCache();
        }

        try {
            $this->checkFingerprint($connection);
            $this->authenticate($connection);
        } catch (Throwable $exception) {
            $connection->disconnect();
            throw $exception;
        }

        return $connection;
    }

    /**
     * Verify the host fingerprint against the expected value.
     *
     * @param SFTP $connection
     * @throws SftpEstablishAuthenticityOfHostException
     */
    private function checkFingerprint(SFTP $connection): void
    {
        if ($this->hostFingerprint === null || $this->hostFingerprint === '') {
            return;
        }

        $publicKey = $connection->getServerPublicHostKey();

        if ($publicKey === false) {
            throw SftpEstablishAuthenticityOfHostException::becauseTheAuthenticityCantBeEstablished($this->host);
        }

        $fingerprint = $this->getFingerprintFromPublicKey($publicKey);

        if (strcasecmp($this->hostFingerprint, $fingerprint) !== 0) {
            throw SftpEstablishAuthenticityOfHostException::becauseTheAuthenticityCantBeEstablished($this->host);
        }
    }

    /**
     * Extract fingerprint from a public key string.
     *
     * @param string $publicKey
     * @return string
     */
    private function getFingerprintFromPublicKey(string $publicKey): string
    {
        $content = explode(' ', $publicKey, 3);
        $algo = $content[0] === 'ssh-rsa' ? 'md5' : 'sha512';

        return implode(':', str_split(hash($algo, base64_decode($content[1])), 2));
    }

    /**
     * Authenticate with the SFTP server using the configured method.
     *
     * @param SFTP $connection
     * @throws SftpAuthenticateException
     * @throws SftpLoadPrivateKeyException
     */
    private function authenticate(SFTP $connection): void
    {
        if ($this->privateKey !== null) {
            $this->authenticateWithPrivateKey($connection);
        } elseif ($this->useAgent) {
            $this->authenticateWithAgent($connection);
        } else {
            $this->authenticateWithUsernameAndPassword($connection);
        }
    }

    /**
     * Authenticate using username and password.
     *
     * @param SFTP $connection
     * @throws SftpAuthenticateException
     */
    private function authenticateWithUsernameAndPassword(SFTP $connection): void
    {
        if (! $connection->login($this->username, $this->password)) {
            throw SftpAuthenticateException::withPassword($connection->getLastError());
        }
    }

    /**
     * Create an SftpConnectionProvider from an array of options.
     *
     * @param array<string, mixed> $options
     * @return self
     */
    public static function fromArray(array $options): self
    {
        return new self(
            host: $options['host'],
            username: $options['username'],
            password: $options['password'] ?? null,
            privateKey: $options['privateKey'] ?? null,
            passphrase: $options['passphrase'] ?? null,
            port: $options['port'] ?? 22,
            useAgent: $options['useAgent'] ?? false,
            timeout: $options['timeout'] ?? 10,
            maxTries: $options['maxTries'] ?? 4,
            hostFingerprint: $options['hostFingerprint'] ?? null,
            connectivityChecker: $options['connectivityChecker'] ?? null,
            preferredAlgorithms: $options['preferredAlgorithms'] ?? [],
        );
    }

    /**
     * Authenticate using a private key.
     *
     * @param SFTP $connection
     * @throws SftpAuthenticateException
     * @throws SftpLoadPrivateKeyException
     */
    private function authenticateWithPrivateKey(SFTP $connection): void
    {
        $privateKey = $this->loadPrivateKey();

        if ($connection->login($this->username, $privateKey)) {
            return;
        }

        if ($this->password !== null && $connection->login($this->username, $this->password)) {
            return;
        }

        throw SftpAuthenticateException::withPrivateKey($connection->getLastError());
    }

    /**
     * Load and parse the private key.
     *
     * @return AsymmetricKey
     * @throws SftpLoadPrivateKeyException
     */
    private function loadPrivateKey(): AsymmetricKey
    {
        if (
            $this->privateKey !== null &&
            str_starts_with($this->privateKey, '---') === false &&
            str_starts_with($this->privateKey, 'PuTTY') === false &&
            is_file($this->privateKey)
        ) {
            $this->privateKey = file_get_contents($this->privateKey);
        }

        try {
            if ($this->passphrase !== null) {
                return PublicKeyLoader::load($this->privateKey, $this->passphrase);
            }

            return PublicKeyLoader::load($this->privateKey);
        } catch (NoKeyLoadedException $exception) {
            throw new SftpLoadPrivateKeyException(null, $exception);
        }
    }

    /**
     * Authenticate using SSH agent.
     *
     * @param SFTP $connection
     * @throws SftpAuthenticateException
     */
    private function authenticateWithAgent(SFTP $connection): void
    {
        $agent = new Agent();

        if (! $connection->login($this->username, $agent)) {
            throw SftpAuthenticateException::withSshAgent($connection->getLastError());
        }
    }
}
