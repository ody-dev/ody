<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Ody\DB\ConnectionPool\ConnectionPoolAdapter;
use Swoole\Coroutine;

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
    protected static $registeredShutdown = false;

    /**
     * Create a new connection instance based on the configuration.
     *
     * @param array $config
     * @param string $name
     * @return \Ody\DB\MySqlConnection
     */
    public static function make(array $config, string $name = 'default')
    {
        logger()->debug("ConnectionFactory::make()");

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

        // Set up deferred cleanup in the current coroutine
        if (Coroutine::getCid() >= 0) {
            Coroutine::defer(function () use ($connection) {
                $connection->disconnect();
            });
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