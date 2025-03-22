<?php

namespace Ody\DB\ConnectionPool;

use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

class ConnectionPoolAdapter
{
    private PDOPool $pool;
    private array $metrics = [
        'borrowed_total' => 0,
        'returned_total' => 0,
        'created_total' => 0,
        'errors_total' => 0,
    ];

    public function __construct(array $config, int $size = 64)
    {
        $pdoConfig = (new PDOConfig)
            ->withHost($config['host'])
            ->withPort($config['port'])
            ->withDbName($config['db_name'])
            ->withCharset($config['charset'] ?? 'utf8mb4')
            ->withUsername($config['username'])
            ->withPassword($config['password']);

        // Add any additional PDO options
        if (!empty($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                $pdoConfig = $pdoConfig->withOption($key, $value);
            }
        }

        $this->pool = new PDOPool($pdoConfig, $size);
        logger()->info("Connection pool initialized with size: $size");
    }

    /**
     * Get a connection from the pool
     *
     * @return false|PDOProxy
     */
    public function borrow(): false|PDOProxy
    {
        $this->metrics['borrowed_total']++;
        return $this->pool->get();
    }

    /**
     * Return a connection to the pool
     *
     * @param \PDO|null $connection
     */
    public function return(?\PDO &$connection): void
    {
        if ($connection === null) {
            // Handle case where connection is already null
            $this->metrics['errors_total']++;
            $this->pool->put(null);
            return;
        }

        $this->metrics['returned_total']++;
        $this->pool->put($connection);
        $connection = null; // Clear the reference
    }

    /**
     * Close the connection pool
     */
    public function close(): void
    {
        $this->pool->close();
    }

    /**
     * Get pool metrics
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'size' => $this->pool->getLength(),
            'idle' => $this->pool->getIdleCount()
        ]);
    }
}