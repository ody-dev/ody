<?php
declare(strict_types=1);

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Ody\ConnectionPool\ConnectionPoolFactory;
use Ody\ConnectionPool\Pool\PoolInterface;
use PDO;
use RuntimeException;
use Swoole\Coroutine;
use Throwable;

class ConnectionManager
{
    /**
     * @var array<string, PoolInterface<PDO>>
     */
    protected static array $pools = [];

    /**
     * @var string
     */
    protected static string $workerId;

    /**
     * Initialize a connection pool for the given configuration
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

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? $config['db_name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        // Calculate optimal pool size based on worker settings
        $connectionsPerWorker = 10; // Adjust this number

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

        $poolFactory->setMinimumIdle(max(2, $connectionsPerWorker / 2));
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
                return !$connection->inTransaction();
            } catch (Throwable) {
                return false;
            }
        });

        $pool = $poolFactory->instantiate(
            "$name-" . self::$workerId
        );

        self::$pools[$name] = $pool;

        register_shutdown_function(function () {
            self::closeAll();
        });

        return $pool;
    }

    /**
     * Set the worker ID if not already set
     *
     * @return void
     */
    protected static function initWorkerId(): void
    {
        if (!isset(self::$workerId)) {
            self::$workerId = getmypid() . '-' . substr(md5(uniqid()), 0, 8);
            logger()->debug("Initializing pool worker with ID: " . self::$workerId);
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
     *
     * @param string $name
     * @return PDO
     * @throws ConnectionPool\Pool\Exceptions\BorrowTimeoutException
     */
    public static function getConnection(string $name = 'default'): PDO
    {
        self::initWorkerId();

        $cid = Coroutine::getCid();
        logger()->debug("Getting connection for coroutine ID: $cid in pool: $name (worker: " . self::$workerId . ")");

        $pool = self::$pools[$name] ?? null;

        if (!$pool) {
            throw new RuntimeException("Connection pool '$name' has not been initialized");
        }

        $conn = $pool->borrow();
        logger()->debug("Borrowed connection from pool: $name, stats: " . json_encode($pool->stats()));

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