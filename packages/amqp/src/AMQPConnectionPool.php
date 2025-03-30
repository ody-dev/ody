<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\Support\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Swoole\Lock;
use Swoole\Timer;
use Throwable;

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
    private array $connections = [];

    /**
     * @var int Maximum idle time for a connection in seconds
     */
    private int $maxIdleTime = 60;

    /**
     * @var int Maximum connections to keep in the pool
     */
    private int $maxPoolSize = 10;

    /**
     * @var bool Whether the garbage collection timer is running
     */
    private bool $gcRunning = false;

    /**
     * @var int Timer interval for garbage collection in milliseconds
     */
    private int $gcIntervalMs = 15000;

    /**
     * @var int|null ID of the GC timer
     */
    private ?int $gcTimerId = null;

    private Lock $mutex;

    /**
     * Constructor to initialize the connection pool
     */
    public function __construct(
        Config                    $config,
        private ConnectionFactory $connectionFactory,
        private LoggerInterface   $logger,
    )
    {
        // Load configuration
        $poolConfig = $config->get('amqp.pool', []);
        $this->maxPoolSize = $poolConfig['max_connections'] ?? 10;
        $this->maxIdleTime = $poolConfig['max_idle_time'] ?? 60;

        $this->mutex = new Lock(Lock::MUTEX);

        // Start garbage collection
        $this->startGarbageCollection();
    }

    /**
     * Get a connection from the pool or create a new one
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPStreamConnection
     * @throws Throwable
     */
    public function getConnection(string $connectionName = 'default'): AMQPStreamConnection
    {
        $this->mutex->lock();

        try {
            $this->startGarbageCollection();

            // Check if we have a valid connection in the pool
            if (isset($this->connections[$connectionName])) {
                $connectionData = $this->connections[$connectionName];

                try {
                    if ($connectionData['connection']->isConnected()) {
                        $connectionData['connection']->checkHeartbeat();
                        $connectionData['lastUsed'] = time();
                        return $connectionData['connection'];
                    }
                } catch (Throwable $e) {
                    // Connection is not healthy, remove it
                    $this->closeConnection($connectionName);

                    $this->logger->error("[AMQP] Connection error: " . $e->getMessage());
                }
            }

            // We need a new connection
            return $this->createNewConnection($connectionName);
        } finally {
            $this->mutex->unlock();
        }
    }

    /**
     * Start garbage collection timer if not already running
     *
     * @return void
     */
    private function startGarbageCollection(): void
    {
        if ($this->gcRunning) {
            return;
        }

        $this->gcTimerId = Timer::tick($this->gcIntervalMs, function () {
            $this->garbageCollect();
        });

        $this->gcRunning = true;
    }

    /**
     * Clean up idle connections
     *
     * @return void
     */
    public function garbageCollect(): void
    {
        $now = time();

        foreach ($this->connections as $name => $data) {
            // Close connections idle for too long
            if ($now - $data['lastUsed'] > $this->maxIdleTime) {
                $this->closeConnection($name);
            }
        }
    }

    /**
     * Close and remove a connection from the pool
     *
     * @param string $connectionName Connection configuration name
     * @return void
     */
    public function closeConnection(string $connectionName): void
    {
        if (!isset($this->connections[$connectionName])) {
            return;
        }

        try {
            $connectionData = $this->connections[$connectionName];

            // Close all channels
            foreach ($connectionData['channels'] as $channel) {
                try {
                    if ($channel->is_open()) {
                        $channel->close();
                    }
                } catch (Throwable $e) {
                    // Ignore channel closing errors
                    $this->logger->error("[AMQP] Error closing channel: " . $e->getMessage());
                }
            }

            // Close the connection
            if ($connectionData['connection']->isConnected()) {
                $connectionData['connection']->close();
            }
        } catch (Throwable $e) {
            // Log but continue
            $this->logger->error("[AMQP] Error closing connection: " . $e->getMessage());
        } finally {
            // Remove from pool regardless of errors
            unset($this->connections[$connectionName]);
        }
    }

    /**
     * Create a new connection and add it to the pool
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPStreamConnection
     */
    private function createNewConnection(string $connectionName): AMQPStreamConnection
    {
        // Close existing connection if it exists but is broken
        if (isset($this->connections[$connectionName])) {
            $this->closeConnection($connectionName);
        }

        // Create new connection
        $connection = $this->connectionFactory->createConnection($connectionName);

        $this->connections[$connectionName] = [
            'connection' => $connection,
            'lastUsed' => time(),
            'channels' => [],
        ];

        // Ensure we don't exceed the pool size
        $this->enforceSizeLimit();

        return $connection;
    }

    /**
     * Enforce the maximum pool size by closing the oldest connections
     *
     * @return void
     */
    private function enforceSizeLimit(): void
    {
        if (count($this->connections) <= $this->maxPoolSize) {
            return;
        }

        // Sort connections by last used time (oldest first)
        uasort($this->connections, function ($a, $b) {
            return $a['lastUsed'] <=> $b['lastUsed'];
        });

        // Close oldest connections until we're under the limit
        $connectionsToRemove = count($this->connections) - $this->maxPoolSize;
        $removed = 0;

        foreach (array_keys($this->connections) as $name) {
            $this->closeConnection($name);
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
    public function registerChannel(string $connectionName, AMQPChannel $channel): void
    {
        if (!isset($this->connections[$connectionName])) {
            return;
        }

        $this->connections[$connectionName]['channels'][] = $channel;
    }

    /**
     * Close all connections in the pool
     *
     * @return void
     */
    public function closeAll(): void
    {
        foreach (array_keys($this->connections) as $name) {
            $this->closeConnection($name);
        }

        // Stop the GC timer
        if ($this->gcTimerId !== null) {
            Timer::clear($this->gcTimerId);
            $this->gcTimerId = null;
            $this->gcRunning = false;
        }
    }

    /**
     * Get pool statistics for monitoring/debugging
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = [
            'total_connections' => count($this->connections),
            'connections' => [],
        ];

        foreach ($this->connections as $name => $data) {
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
    public function setMaxPoolSize(int $size): void
    {
        $this->maxPoolSize = max(1, $size);
        $this->enforceSizeLimit();
    }

    /**
     * Set the maximum idle time for connections
     *
     * @param int $seconds Maximum idle time in seconds
     * @return void
     */
    public function setMaxIdleTime(int $seconds): void
    {
        $this->maxIdleTime = max(10, $seconds);
    }

    /**
     * Set the garbage collection interval
     *
     * @param int $milliseconds Garbage collection interval in milliseconds
     * @return void
     */
    public function setGarbageCollectionInterval(int $milliseconds): void
    {
        $this->gcIntervalMs = max(1000, $milliseconds);

        // Restart GC with new interval if running
        if ($this->gcRunning && $this->gcTimerId !== null) {
            Timer::clear($this->gcTimerId);
            $this->gcRunning = false;
            $this->startGarbageCollection();
        }
    }

    /**
     * Destructor to ensure timers are cleaned up
     */
    public function __destruct()
    {
        if ($this->gcTimerId !== null) {
            Timer::clear($this->gcTimerId);
        }
    }
}