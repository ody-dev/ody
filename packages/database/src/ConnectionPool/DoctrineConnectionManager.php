<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\ConnectionPool;

use Doctrine\DBAL\DriverManager;
use Ody\DB\Doctrine\DoctrineConnection;
use Swoole\Coroutine;

class DoctrineConnectionManager
{
    /**
     * The pool instances for each connection
     *
     * @var array<string, ConnectionPoolAdapter>
     */
    private static array $pools = [];

    /**
     * The active connections for each coroutine
     *
     * @var array
     */
    private static array $activeConnections = [];

    /**
     * Flag to track if we've registered the shutdown function
     *
     * @var bool
     */
    private static bool $registeredShutdown = false;

    /**
     * Initialize a connection pool
     *
     * @param array $config
     * @param string $name
     * @param int $poolSize
     * @return void
     */
    public static function initialize(array $config, string $name = 'default', int $poolSize = 64): void
    {
        // Register shutdown function only once
        if (!self::$registeredShutdown) {
            register_shutdown_function(function () {
                self::closeAll();
            });
            self::$registeredShutdown = true;
        }

        // Only create the pool if it doesn't exist
        if (!isset(self::$pools[$name])) {
            logger()->info("Creating Doctrine connection pool for '$name'");

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
        }
    }

    /**
     * Get a connection for the current coroutine
     *
     * @param string $name
     * @param array $config
     * @return DoctrineConnection
     */
    public static function getConnection(string $name = 'default', array $config = []): DoctrineConnection
    {
        $cid = Coroutine::getCid();

        if ($cid === -1) {
            // Not in a coroutine, create a standard connection
            logger()->debug("Not in a coroutine, creating standard Doctrine connection");
            return self::createStandardConnection($config);
        }

        // If we already have a connection for this coroutine, return it
        if (isset(self::$activeConnections[$name][$cid])) {
            return self::$activeConnections[$name][$cid];
        }

        // Ensure the pool is initialized
        if (!isset(self::$pools[$name])) {
            if (empty($config)) {
                throw new \RuntimeException("Connection pool '$name' not initialized and no config provided");
            }
            self::initialize($config, $name);
        }

        // Borrow a connection from the pool
        $pdo = self::$pools[$name]->borrow();

        // Create a new Doctrine connection instance
        $connectionParams = [
            'pdo' => $pdo,
            'wrapperClass' => DoctrineConnection::class,
            'dbname' => $config['database'] ?? $config['db_name'] ?? '',
        ];

        $connection = DriverManager::getConnection($connectionParams);

        // Set the pool adapter on the connection
        if ($connection instanceof DoctrineConnection) {
            $connection->setPoolAdapter(self::$pools[$name]);
        }

        // Store the connection for this coroutine
        if (!isset(self::$activeConnections[$name])) {
            self::$activeConnections[$name] = [];
        }
        self::$activeConnections[$name][$cid] = $connection;

        // Set up deferred cleanup for when the coroutine ends
        Coroutine::defer(function () use ($name, $cid) {
            if (isset(self::$activeConnections[$name][$cid])) {
                $connection = self::$activeConnections[$name][$cid];

                // Close/return connection to pool
                $connection->close();

                // Remove from active connections
                unset(self::$activeConnections[$name][$cid]);
            }
        });

        return $connection;
    }

    /**
     * Create a standard Doctrine connection without pooling
     *
     * @param array $config
     * @return DoctrineConnection
     */
    private static function createStandardConnection(array $config): DoctrineConnection
    {
        $connectionParams = [
            'driver'   => $config['driver'] ?? 'pdo_mysql',
            'host'     => $config['host'] ?? 'localhost',
            'port'     => $config['port'] ?? 3306,
            'dbname'   => $config['database'] ?? $config['db_name'] ?? '',
            'user'     => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'charset'  => $config['charset'] ?? 'utf8mb4',
            'wrapperClass' => DoctrineConnection::class,
        ];

        return DriverManager::getConnection($connectionParams);
    }

    /**
     * Close all connection pools
     *
     * @return void
     */
    public static function closeAll(): void
    {
        logger()->info("Closing all Doctrine connection pools");

        // Close all active connections first
        foreach (self::$activeConnections as $name => $connections) {
            foreach ($connections as $cid => $connection) {
                $connection->close();
                unset(self::$activeConnections[$name][$cid]);
            }
        }

        // Then close all pools
        foreach (self::$pools as $name => $pool) {
            logger()->debug("Closing Doctrine connection pool: $name");
            $pool->close();
        }

        self::$pools = [];
        self::$activeConnections = [];
    }
}