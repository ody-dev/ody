<?php

namespace Ody\DB;

use Allsilaevex\ConnectionPool\ConnectionFactories\PDOConnectionFactory;
use Allsilaevex\ConnectionPool\ConnectionPoolFactory;
use Allsilaevex\Pool\PoolInterface;
use PDO;
use Swoole\Coroutine;

class ConnectionManager
{
    /**
     * @var array<string, PoolInterface<PDO>>
     */
    protected static array $pools = [];

    protected static string $workerId;

    /**
     * Initialize a connection pool for the given configuration
     */
    public static function initPool(array $config, string $name = 'default'): PoolInterface
    {
        self::initWorkerId();

        if (isset(self::$pools[$name])) {
            return self::$pools[$name];
        }

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? $config['db_name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        // Calculate optimal pool size based on worker settings
        $connectionsPerWorker = 20; // Adjust this number

//        logger()->info("Initializing connection pool for worker $workerId with $connectionsPerWorker connections");

        // Create a pool with multiple connections per worker
        $poolFactory = ConnectionPoolFactory::create(
            size: $connectionsPerWorker,
            factory: new PDOConnectionFactory(
                dsn: $dsn,
                username: $config['username'] ?? '',
                password: $config['password'] ?? '',
                options: $config['options'] ?? [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
            )
        );

        // Configure the pool - adjusted for higher concurrency
        $poolFactory->setMinimumIdle(max(2, $connectionsPerWorker / 2)); // Keep at least half connections idle
        $poolFactory->setIdleTimeoutSec($config['idle_timeout'] ?? 60.0);
        $poolFactory->setMaxLifetimeSec($config['max_lifetime'] ?? 3600.0);
        $poolFactory->setBorrowingTimeoutSec($config['borrowing_timeout'] ?? 0.5);
        $poolFactory->setReturningTimeoutSec($config['returning_timeout'] ?? 0.1);
        $poolFactory->setLeakDetectionThresholdSec($config['leak_detection_threshold'] ?? 10.0);
        $poolFactory->setAutoReturn(true);
        $poolFactory->setBindToCoroutine(true);

        // Add a connection checker to verify connections aren't in a transaction
        $poolFactory->addConnectionChecker(function (PDO $connection): bool {
            try {
                // Check if a transaction is active
                return !$connection->inTransaction();
            } catch (\Throwable) {
                // If any error occurs, the connection is considered bad
                return false;
            }
        });

        // Create the pool with a unique name for this worker
        $poolName = "$name-" . self::$workerId;
        $pool = $poolFactory->instantiate($poolName);

        // Store it for future use
        self::$pools[$name] = $pool;

        // Register shutdown handler if not already registered
        register_shutdown_function(function () {
            self::closeAll();
        });

        return $pool;
    }

    /**
     * Initialize the worker ID if not already set
     */
    protected static function initWorkerId(): void
    {
        if (!isset(self::$workerId)) {
            // Create a unique ID for this worker process
            // Uses process ID plus a random value to ensure uniqueness
            self::$workerId = getmypid() . '-' . substr(md5(uniqid()), 0, 8);
            logger()->info("Initializing worker with ID: " . self::$workerId);
        }
    }

    /**
     * Close all connection pools
     */
    public static function closeAll(): void
    {
        foreach (self::$pools as $name => $pool) {
            logger()->debug("Closing connection pool: $name");
        }
        self::$pools = [];
    }

    /**
     * Get a PDO connection from the pool
     */
    public static function getConnection(string $name = 'default'): PDO
    {
        self::initWorkerId();

        $cid = Coroutine::getCid();
        logger()->info("Getting connection for coroutine ID: $cid in pool: $name (worker: " . self::$workerId . ")");

        $pool = self::$pools[$name] ?? null;

        if (!$pool) {
            throw new \RuntimeException("Connection pool '$name' has not been initialized");
        }

        $conn = $pool->borrow();
        logger()->info("Borrowed connection from pool: $name, stats: " . json_encode($pool->stats()));

        return $conn;
    }

    /**
     * Get the pool instance
     */
    public static function getPool(string $name = 'default'): ?PoolInterface
    {
        return self::$pools[$name] ?? null;
    }
}