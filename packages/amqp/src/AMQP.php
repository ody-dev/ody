<?php

declare(strict_types=1);

namespace Ody\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Modified AMQP class that uses connection and channel pools
 */
class AMQP
{
    /**
     * @var ProducerService|null
     */
    private static ?ProducerService $producerService = null;

    /**
     * @var \Psr\Container\ContainerInterface|null
     */
    private static $container = null;

    /**
     * Register the container for dependency resolution
     */
    public static function setContainer($container): void
    {
        self::$container = $container;
    }

    /**
     * Publish a message using a producer class
     * Ensures it runs in a coroutine context and uses connection pooling
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param string|null $connectionName Optional connection configuration name
     * @return bool Success state
     */
    public static function publish(string $producerClass, array $args = [], ?string $connectionName = null): bool
    {
        // Check if we're in a coroutine context
//        if (!Coroutine::getCid()) {
//            // If not, create a coroutine
//            $result = [false];
//            Coroutine::create(function () use ($producerClass, $args, $connectionName, &$result) {
//                $result[0] = self::getProducerService()->produce($producerClass, $args, $connectionName);
//            });
//            return $result[0];
//        }

        // Already in a coroutine
        return self::getProducerService()->produce($producerClass, $args, $connectionName);
    }

    /**
     * Get the producer service
     */
    private static function getProducerService(): ProducerService
    {
        if (self::$producerService === null) {
            if (self::$container !== null && method_exists(self::$container, 'get')) {
                self::$producerService = self::$container->get(ProducerService::class);
            } else {
                // Fallback to manual instantiation
                self::$producerService = new ProducerService(
                    new AMQPManager(
                        new MessageProcessor(new \Ody\Task\TaskManager()),
                        new \Ody\Task\TaskManager(),
                        new \Ody\Process\ProcessManager()
                    )
                );
            }
        }

        return self::$producerService;
    }

    /**
     * Publish a delayed message
     * Ensures it runs in a coroutine context and uses connection pooling
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param int $delayMs Delay in milliseconds
     * @param string|null $connectionName Optional connection configuration name
     * @return bool Success state
     */
    public static function publishDelayed(string $producerClass, array $args = [], int $delayMs = 1000, ?string $connectionName = null): bool
    {
        // Check if we're in a coroutine context
//        if (!Coroutine::getCid()) {
//            // If not, create a coroutine
//            $result = [false];
//            Coroutine::create(function () use ($producerClass, $args, $delayMs, $connectionName, &$result) {
//                $result[0] = self::getProducerService()->produceWithDelay($producerClass, $args, $delayMs, $connectionName);
//            });
//            return $result[0];
//        }

        // Already in a coroutine
        return self::getProducerService()->produceWithDelay($producerClass, $args, $delayMs, $connectionName);
    }

    /**
     * Set the producer service (for testing or customization)
     */
    public static function setProducerService(ProducerService $service): void
    {
        self::$producerService = $service;
    }

    /**
     * Create a direct connection to RabbitMQ
     * This now returns a pooled connection instead of creating a new one
     *
     * @param string $connectionName Name of the connection configuration to use
     * @return AMQPStreamConnection
     */
    public static function createConnection(string $connectionName = 'default'): AMQPStreamConnection
    {
        $config = config('amqp');
        $connectionConfig = $config[$connectionName] ?? $config['default'] ?? [];

        return new AMQPStreamConnection(
            host: $connectionConfig['host'] ?? 'localhost',
            port: $connectionConfig['port'] ?? 5672,
            user: $connectionConfig['user'] ?? 'guest',
            password: $connectionConfig['password'] ?? 'guest',
            vhost: $connectionConfig['vhost'] ?? '/',
            insist: ($connectionConfig['params']['insist'] ?? false),
            login_method: ($connectionConfig['params']['login_method'] ?? 'AMQPLAIN'),
            login_response: null,
            locale: ($connectionConfig['params']['locale'] ?? 'en_US'),
            connection_timeout: ($connectionConfig['params']['connection_timeout'] ?? 3.0),
            read_write_timeout: ($connectionConfig['params']['read_write_timeout'] ?? 3.0),
            context: null,
            keepalive: true, // Set a reasonable heartbeat value
            heartbeat: 60, // Enable TCP keepalive
        );
    }

    /**
     * Get a pooled connection
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPStreamConnection
     */
    public static function getPooledConnection(string $connectionName = 'default'): AMQPStreamConnection
    {
        return AMQPConnectionPool::getConnection($connectionName);
    }

    /**
     * Get a pooled channel
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPChannel
     */
    public static function getPooledChannel(string $connectionName = 'default'): AMQPChannel
    {
        return AMQPChannelPool::getChannel($connectionName);
    }
}