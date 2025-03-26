<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\ConnectionPool\RabbitMQConnectionFactory;
use Ody\ConnectionPool\ConnectionPoolFactory;
use Ody\ConnectionPool\Pool\PoolInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Swoole\Coroutine;
use Throwable;

class ConnectionManager
{
    /**
     * @var array<string, PoolInterface<AMQPStreamConnection>>
     */
    protected static array $pools = [];

    /**
     * @var string Worker ID for unique pool identification
     */
    protected static string $workerId;

    /**
     * @var array<string, array> Stored pool configurations
     */
    protected static array $poolConfigs = [];

    /**
     * @var bool Flag indicating if pools are initialized
     */
    protected static bool $initialized = false;

    /**
     * Store pool configuration for lazy initialization
     *
     * @param array $config
     * @param string $name
     * @return void
     */
    public static function storePoolConfig(array $config, string $name = 'default'): void
    {
        self::$poolConfigs[$name] = $config;
    }

    /**
     * Initialize worker ID
     */
    private static function initWorkerId(): void
    {
        if (!isset(self::$workerId)) {
            if (function_exists('swoole_cpu_num')) {
                $workerId = getmypid() % swoole_cpu_num();
            } else {
                $workerId = getmypid();
            }
            self::$workerId = "worker-$workerId";
        }
    }

    /**
     * Initialize a connection pool for the given configuration
     * This should only be called within a coroutine context
     *
     * @param array $config
     * @param string $name
     * @return PoolInterface
     */
    public static function initPool(array $config, string $name = 'default'): PoolInterface
    {
        self::initWorkerId();

        if (isset(self::$pools[$name])) {
            return self::$pools[$name];
        }

        // Calculate optimal pool size based on worker settings
        $connectionsPerWorker = $config['pool']['connections'] ?? 5;

        // Create a pool with multiple connections per worker
        $poolFactory = ConnectionPoolFactory::create(
            size: $connectionsPerWorker,
            factory: new RabbitMQConnectionFactory(
                host: $config['host'] ?? 'localhost',
                port: $config['port'] ?? 5672,
                user: $config['user'] ?? 'guest',
                password: $config['password'] ?? 'guest',
                vhost: $config['vhost'] ?? '/',
                options: $config['params'] ?? []
            )
        );

        $poolFactory->setMinimumIdle((int)max(2, $connectionsPerWorker / 2));
        $poolFactory->setIdleTimeoutSec($config['idle_timeout'] ?? 60.0);
        $poolFactory->setMaxLifetimeSec($config['max_lifetime'] ?? 3600.0);
        $poolFactory->setBorrowingTimeoutSec($config['borrowing_timeout'] ?? 0.5);
        $poolFactory->setReturningTimeoutSec($config['returning_timeout'] ?? 0.1);
        $poolFactory->setLeakDetectionThresholdSec($config['leak_detection_threshold'] ?? 10.0);
        $poolFactory->setAutoReturn(true);
        $poolFactory->setBindToCoroutine(true);

        // Add a connection checker to verify connections are still alive
        $poolFactory->addConnectionChecker(function (AMQPStreamConnection $connection): bool {
            try {
                return $connection->isConnected();
            } catch (Throwable) {
                return false;
            }
        });

        $pool = $poolFactory->instantiate(
            "$name-" . self::$workerId
        );

        self::$pools[$name] = $pool;

        // Register shutdown function to close all connections
        register_shutdown_function(function () {
            self::closeAll();
        });

        return $pool;
    }

    /**
     * Get a connection from the pool, initializing it if needed
     * This is the main entry point that ensures lazy initialization
     */
    public static function getConnection(string $poolName = 'default'): AMQPStreamConnection
    {
        // Check if we're in a coroutine context
        if (!Coroutine::getCid()) {
            throw new \RuntimeException("AMQP operations must be performed within a coroutine");
        }

        // Initialize pool if not already done
        if (!isset(self::$pools[$poolName])) {
            if (!isset(self::$poolConfigs[$poolName])) {
                throw new \RuntimeException("Connection pool '$poolName' not configured. Did you register it?");
            }

            // Initialize the pool using the stored configuration
            self::initPool(self::$poolConfigs[$poolName], $poolName);
        }

        return self::$pools[$poolName]->borrow();
    }

    /**
     * Return a connection to the pool
     */
    public static function returnConnection(AMQPStreamConnection $connection, string $poolName = 'default'): void
    {
        if (!isset(self::$pools[$poolName])) {
            // If the pool doesn't exist, just try to close the connection
            try {
                $connection->close();
            } catch (Throwable) {
                // Ignore close errors
            }
            return;
        }

        self::$pools[$poolName]->return($connection);
    }

    /**
     * Close all connections
     */
    public static function closeAll(): void
    {
        foreach (self::$pools as $pool) {
            try {
                $pool->close();
            } catch (Throwable) {
                // Ignore close errors
            }
        }
    }
}