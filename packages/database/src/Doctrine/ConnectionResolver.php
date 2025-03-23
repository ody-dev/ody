<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

/**
 * Resolves Doctrine DBAL connections with Swoole connection pooling
 */
class ConnectionResolver
{
    /**
     * The connection pool adapters
     *
     * @var array<string, ConnectionPoolAdapter>
     */
    protected static array $pools = [];

    /**
     * Active connections per coroutine
     *
     * @var array
     */
    protected static array $activeConnections = [];

    /**
     * Whether the shutdown function is registered
     *
     * @var bool
     */
    protected static bool $registeredShutdown = false;

    /**
     * Initialize the connection pooling system
     */
    public static function initialize(): void
    {
        if (!self::$registeredShutdown) {
            register_shutdown_function(function () {
                self::closeAll();
            });
            self::$registeredShutdown = true;
        }
    }

    /**
     * Register a custom connection factory with Doctrine DBAL
     *
     * @return void
     */
    public static function registerConnectionFactory(): void
    {
        // Store the original factory
        $originalFactory = DriverManager::getConnection(...);

        // Override the DriverManager::getConnection method
        DriverManager::$_driverManager = new class($originalFactory) {
            private $originalFactory;

            public function __construct(callable $originalFactory)
            {
                $this->originalFactory = $originalFactory;
            }

            public function __invoke(array $params, ?Configuration $config = null, $eventManager = null)
            {
                // Check if we should use a pooled connection
                $usePooling = ($params['use_pooling'] ?? false) && extension_loaded('swoole');

                if ($usePooling && Coroutine::getCid() >= 0) {
                    // Return a pooled connection
                    return ConnectionResolver::resolveConnection($params, $config, $eventManager);
                }

                // Otherwise use the original factory
                return ($this->originalFactory)($params, $config, $eventManager);
            }
        };
    }

    /**
     * Resolve a pooled connection
     *
     * @param array $params
     * @param Configuration|null $config
     * @param mixed $eventManager
     * @return Connection
     */
    public static function resolveConnection(array $params, ?Configuration $config = null, $eventManager = null): Connection
    {
        $cid = Coroutine::getCid();
        $name = $params['connection_name'] ?? 'default';

        // Return an existing connection for this coroutine if one exists
        if (isset(self::$activeConnections[$name][$cid])) {
            return self::$activeConnections[$name][$cid];
        }

        // Get or create pool
        $pool = self::getPool($params);

        // Borrow a connection from the pool
        $pdo = $pool->borrow();

        // Use the provided wrapperClass or our own
        $wrapperClass = $params['wrapperClass'] ?? PooledConnection::class;

        // Create connection params with the PDO we got from the pool
        $connectionParams = array_merge($params, [
            'pdo' => $pdo,
            'wrapperClass' => $wrapperClass
        ]);

        // Create the connection with the original factory
        $connection = DriverManager::getConnectionOrig($connectionParams, $config, $eventManager);

        // If it's our wrapper class, configure it to use the pool
        if ($connection instanceof PooledConnection) {
            $connection->setPoolAdapter($pool);
        }

        // Store connection for this coroutine
        if (!isset(self::$activeConnections[$name])) {
            self::$activeConnections[$name] = [];
        }
        self::$activeConnections[$name][$cid] = $connection;

        // Set up deferred cleanup
        Coroutine::defer(function() use ($name, $cid) {
            if (isset(self::$activeConnections[$name][$cid])) {
                $connection = self::$activeConnections[$name][$cid];

                // If it's our wrapper, call close which will return the connection to the pool
                if ($connection instanceof PooledConnection) {
                    $connection->close();
                }

                // Remove from active connections
                unset(self::$activeConnections[$name][$cid]);
            }
        });

        return $connection;
    }

    /**
     * Get or create a connection pool
     *
     * @param array $params
     * @return ConnectionPoolAdapter
     */
    public static function getPool(array $params): ConnectionPoolAdapter
    {
        $name = $params['connection_name'] ?? 'default';

        // Create the pool if it doesn't exist
        if (!isset(self::$pools[$name])) {
            logger()->info("Creating Doctrine connection pool for '$name'");

            $poolSize = $params['pool_size'] ?? 64;

            // Create adapter config
            $adapterConfig = [
                'host' => $params['host'] ?? 'localhost',
                'port' => $params['port'] ?? 3306,
                'db_name' => $params['dbname'] ?? $params['database'] ?? '',
                'charset' => $params['charset'] ?? 'utf8mb4',
                'username' => $params['user'] ?? $params['username'] ?? '',
                'password' => $params['password'] ?? '',
                'options' => $params['driverOptions'] ?? $params['options'] ?? [],
            ];

            self::$pools[$name] = new ConnectionPoolAdapter($adapterConfig, $poolSize);
        }

        return self::$pools[$name];
    }

    /**
     * Close all connection pools
     */
    public static function closeAll(): void
    {
        logger()->info("Closing all Doctrine connection pools");

        // Close all active connections
        foreach (self::$activeConnections as $name => $connections) {
            foreach ($connections as $cid => $connection) {
                if ($connection instanceof PooledConnection) {
                    $connection->close();
                }
                unset(self::$activeConnections[$name][$cid]);
            }
        }

        // Close all pools
        foreach (self::$pools as $name => $pool) {
            logger()->debug("Closing Doctrine connection pool: $name");
            $pool->close();
        }

        self::$pools = [];
        self::$activeConnections = [];
    }
}