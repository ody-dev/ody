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
    /**
     * Create a new connection instance based on the configuration.
     */
    public static function make(array $config, string $name = 'default')
    {
        // Initialize the pool if needed
        ConnectionManager::initPool($config, $name);

        // Get a connection from the pool
        $pdo = ConnectionManager::getConnection($name);

        // Create the Eloquent connection
        return new MySqlConnection(
            $pdo,
            $config['database'] ?? $config['db_name'] ?? '',
            $config['prefix'] ?? '',
            $config
        );
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