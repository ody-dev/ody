<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\ConnectionPool\RabbitMQConnectionFactory;
use Ody\ConnectionPool\ConnectionPoolFactory;
use Ody\ConnectionPool\Pool\PoolInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

class ConnectionManager
{
    /**
     * @var array<string, PoolInterface<AMQPStreamConnection>>
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

        $poolFactory->setMinimumIdle(max(2, $connectionsPerWorker / 2));
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

        register_shutdown_function(function () {
            self::closeAll();
        });

        return $pool;
    }
}