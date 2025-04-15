<?php

namespace Ody\ConnectionPool\Pool;

use Ody\ConnectionPool\KeepaliveCheckerInterface;
use PDO;

/**
 * @template TConnection of object
 */
class KeepAliveChecker implements KeepaliveCheckerInterface
{
    /**
     * @var array <string, mixed>
     */
    private array $config;

    /**
     * @param array<string, string|int|float> $config
     */
    public function __construct(array $config)
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