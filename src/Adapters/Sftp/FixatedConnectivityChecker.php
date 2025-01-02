<?php

declare(strict_types=1);

namespace Cleup\Filesystem\Adapters\Sftp;

use Cleup\Filesystem\Interfaces\SftpConnectivityCheckerInterface;
use phpseclib3\Net\SFTP;

class FixatedConnectivityChecker implements SftpConnectivityCheckerInterface
{
    /**
     * @var int
     */
    private $succeedAfter;

    /**
     * @var int
     */
    private $numberOfTimesChecked = 0;

    public function __construct(int $succeedAfter = 0)
    {
        $this->succeedAfter = $succeedAfter;
    }

    public function isConnected(SFTP $connection): bool
    {
        if ($this->numberOfTimesChecked >= $this->succeedAfter) {
            return true;
        }

        $this->numberOfTimesChecked++;

        return false;
    }
}
