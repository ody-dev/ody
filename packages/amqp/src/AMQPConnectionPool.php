<?php

declare(strict_types=1);

namespace Ody\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Swoole\Timer;

/**
 * Connection pool for AMQP connections
 * This class manages a pool of reusable connections to reduce the overhead
 * of creating new connections for each message.
 */
class AMQPConnectionPool
{
    /**
     * @var array<string, array> Connection pool [connectionName => [connection, lastUsed, channels]]
     */
    private static array $connections = [];

    /**
     * @var int Maximum idle time for a connection in seconds
     */
    private static int $maxIdleTime = 60;

    /**
     * @var int Maximum connections to keep in the pool
     */
    private static int $maxPoolSize = 10;

    /**
     * @var bool Whether the garbage collection timer is running
     */
    private static bool $gcRunning = false;

    /**
     * @var int Timer interval for garbage collection in milliseconds
     */
    private static int $gcIntervalMs = 15000;

    /**
     * Get a connection from the pool or create a new one
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPStreamConnection
     */
    public static function getConnection(string $connectionName = 'default'): AMQPStreamConnection
    {
        self::startGarbageCollection();

        // Check if we have a valid connection in the pool
        if (isset(self::$connections[$connectionName]) &&
            self::$connections[$connectionName]['connection']->isConnected()) {

            // Update last used time
            self::$connections[$connectionName]['lastUsed'] = time();
            return self::$connections[$connectionName]['connection'];
        }

        // Create a new connection
        return self::createNewConnection($connectionName);
    }

    /**
     * Start garbage collection timer if not already running
     *
     * @return void
     */
    private static function startGarbageCollection(): void
    {
        if (self::$gcRunning) {
            return;
        }

        Timer::tick(self::$gcIntervalMs, function () {
            self::garbageCollect();
        });

        self::$gcRunning = true;
    }

    /**
     * Clean up idle connections
     *
     * @return void
     */
    public static function garbageCollect(): void
    {
        $now = time();

        foreach (self::$connections as $name => $data) {
            // Close connections idle for too long
            if ($now - $data['lastUsed'] > self::$maxIdleTime) {
                self::closeConnection($name);
            }
        }
    }

    /**
     * Close and remove a connection from the pool
     *
     * @param string $connectionName Connection configuration name
     * @return void
     */
    public static function closeConnection(string $connectionName): void
    {
        if (!isset(self::$connections[$connectionName])) {
            return;
        }

        try {
            $connectionData = self::$connections[$connectionName];

            // Close all channels
            foreach ($connectionData['channels'] as $channel) {
                try {
                    if ($channel->is_open()) {
                        $channel->close();
                    }
                } catch (\Throwable $e) {
                    // Ignore channel closing errors
                    logger()->error("[AMQP] Error closing channel: " . $e->getMessage());
                }
            }

            // Close the connection
            if ($connectionData['connection']->isConnected()) {
                $connectionData['connection']->close();
            }
        } catch (\Throwable $e) {
            // Log but continue
            logger()->error("[AMQP] Error closing connection: " . $e->getMessage());
        } finally {
            // Remove from pool regardless of errors
            unset(self::$connections[$connectionName]);
        }
    }

    /**
     * Create a new connection and add it to the pool
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPStreamConnection
     */
    private static function createNewConnection(string $connectionName): AMQPStreamConnection
    {
        // Close existing connection if it exists but is broken
        if (isset(self::$connections[$connectionName])) {
            self::closeConnection($connectionName);
        }

        // Create new connection
        $connection = AMQP::createConnection($connectionName);

        self::$connections[$connectionName] = [
            'connection' => $connection,
            'lastUsed' => time(),
            'channels' => [],
        ];

        // Ensure we don't exceed the pool size
        self::enforceSizeLimit();

        return $connection;
    }

    /**
     * Enforce the maximum pool size by closing the oldest connections
     *
     * @return void
     */
    private static function enforceSizeLimit(): void
    {
        if (count(self::$connections) <= self::$maxPoolSize) {
            return;
        }

        // Sort connections by last used time (oldest first)
        uasort(self::$connections, function ($a, $b) {
            return $a['lastUsed'] <=> $b['lastUsed'];
        });

        // Close oldest connections until we're under the limit
        $connectionsToRemove = count(self::$connections) - self::$maxPoolSize;
        $removed = 0;

        foreach (array_keys(self::$connections) as $name) {
            self::closeConnection($name);
            $removed++;

            if ($removed >= $connectionsToRemove) {
                break;
            }
        }
    }

    /**
     * Register a channel with a connection
     *
     * @param string $connectionName Connection configuration name
     * @param AMQPChannel $channel Channel to register
     * @return void
     */
    public static function registerChannel(string $connectionName, AMQPChannel $channel): void
    {
        if (!isset(self::$connections[$connectionName])) {
            return;
        }

        self::$connections[$connectionName]['channels'][] = $channel;
    }

    /**
     * Close all connections in the pool
     *
     * @return void
     */
    public static function closeAll(): void
    {
        foreach (array_keys(self::$connections) as $name) {
            self::closeConnection($name);
        }
    }

    /**
     * Get pool statistics for monitoring/debugging
     *
     * @return array
     */
    public static function getStats(): array
    {
        $stats = [
            'total_connections' => count(self::$connections),
            'connections' => [],
        ];

        foreach (self::$connections as $name => $data) {
            $stats['connections'][$name] = [
                'connected' => $data['connection']->isConnected(),
                'last_used' => date('Y-m-d H:i:s', $data['lastUsed']),
                'idle_time' => time() - $data['lastUsed'],
                'channel_count' => count($data['channels']),
            ];
        }

        return $stats;
    }

    /**
     * Set the maximum pool size
     *
     * @param int $size Maximum number of connections to keep in the pool
     * @return void
     */
    public static function setMaxPoolSize(int $size): void
    {
        self::$maxPoolSize = max(1, $size);
        self::enforceSizeLimit();
    }

    /**
     * Set the maximum idle time for connections
     *
     * @param int $seconds Maximum idle time in seconds
     * @return void
     */
    public static function setMaxIdleTime(int $seconds): void
    {
        self::$maxIdleTime = max(10, $seconds);
    }
}