<?php

namespace Ody\DB;

use Ody\DB\ConnectionPool\ConnectionPoolAdapter;

class ConnectionFactory
{
    /**
     * The pool instances
     *
     * @var array<string, ConnectionPoolAdapter>
     */
    protected static $pools = [];

    /**
     * Flag to track if we've registered the shutdown function
     *
     * @var bool
     */
    protected static bool $registeredShutdown = false;

    /**
     * @var array
     */
    protected static array $coroutineConnections = [];

    /**
     * Create a new connection instance based on the configuration.
     *
     * @param array $config
     * @param string $name
     * @return \Ody\DB\MySqlConnection
     */
    public static function make(array $config, string $name = 'default')
    {
        $cid = \Swoole\Coroutine::getCid();

        // If we already have a connection for this coroutine, return it
        if (isset(static::$coroutineConnections[$cid][$name])) {
            return static::$coroutineConnections[$cid][$name];
        }

        // Create or get the pool for this connection
        $pool = self::getPool($config, $name);

        // Get a connection from the pool
        $pdo = $pool->borrow();

        // Create the connection instance
        $connection = new MySqlConnection(
            $pdo,
            $config['database'] ?? $config['db_name'] ?? '',
            $config['prefix'] ?? '',
            $config
        );

        // Set the pool adapter on the connection
        $connection->setPoolAdapter($pool);

        // Store it for this coroutine
        static::$coroutineConnections[$cid][$name] = $connection;

        // Set up deferred cleanup when the coroutine ends
        if (!isset(static::$coroutineConnections[$cid]['__cleanup_registered'])) {
            \Swoole\Coroutine::defer(function () use ($cid) {
                // Disconnect all connections for this coroutine
                if (isset(static::$coroutineConnections[$cid])) {
                    foreach (static::$coroutineConnections[$cid] as $conn) {
                        if ($conn instanceof Connection) {
                            $conn->disconnect();
                        }
                    }
                    unset(static::$coroutineConnections[$cid]);
                }
            });

            static::$coroutineConnections[$cid]['__cleanup_registered'] = true;
        }

        return $connection;
    }

    /**
     * Get or create a connection pool for the given config
     *
     * @param array $config
     * @param string $name
     * @return ConnectionPoolAdapter
     */
    public static function getPool(array $config, string $name)
    {
        // Register the shutdown function only once
        if (!self::$registeredShutdown) {
            register_shutdown_function(function () {
                self::closeAll();
            });
            self::$registeredShutdown = true;
        }

        // Only create the pool if it doesn't exist
        if (!isset(self::$pools[$name])) {
            $lock = new \Swoole\Lock(SWOOLE_MUTEX);
            $lock->lock();

            try {
                logger()->info("Creating connection pool for '$name'");

                $poolSize = $config['pool_size'] ?? 32;

                // Make sure the config has all the required keys for the adapter
                $adapterConfig = [
                    'host' => $config['host'] ?? 'localhost',
                    'port' => $config['port'] ?? 3306,
                    'db_name' => $config['database'] ?? $config['db_name'] ?? '',
                    'charset' => $config['charset'] ?? 'utf8mb4',
                    'username' => $config['username'] ?? '',
                    'password' => $config['password'] ?? '',
                    'options' => $config['options'] ?? [],
                ];

                self::$pools[$name] = new ConnectionPoolAdapter($adapterConfig, $poolSize);
            } finally {
                $lock->unlock();
            }
        } else {
            logger()->debug("Reusing existing connection pool for '$name'");
        }

        return self::$pools[$name];
    }

    /**
     * Close all connection pools
     */
    public static function closeAll()
    {
        logger()->info("Closing all connection pools");
        foreach (self::$pools as $name => $pool) {
            logger()->debug("Closing connection pool: $name");
            $pool->close();
        }
        self::$pools = [];
    }
}