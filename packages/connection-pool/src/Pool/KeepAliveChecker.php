<?php

namespace Ody\ConnectionPool\Pool;

use Ody\ConnectionPool\KeepaliveCheckerInterface;
use PDO;

/**
 * @implements KeepaliveCheckerInterface<PDO>
 */
class KeepAliveChecker implements KeepaliveCheckerInterface
{
    private array $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function check(?PDO $connection): bool
    {
        if (!$connection instanceof \PDO) return false;
        try {
            $connection->getAttribute(\PDO::ATTR_SERVER_INFO);
            return true;
        } catch (\Throwable) {
            return false; // Connection is likely dead
        }
    }

    public function getIntervalSec(): float
    {
        if (isset($this->config['pool']['keep_alive_check_interval'])) {
            return (float)$this->config['pool']['keep_alive_check_interval'];
        }
        // Has to be lower than MySQL wait_timeout
        return 60;
    }
}