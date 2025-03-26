<?php

declare(strict_types=1);

namespace Ody\AMQP;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Swoole\Coroutine;

//use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Facade for AMQP functionality
 */
class AMQP
{
    /**
     * @var ProducerService|null
     */
    private static ?ProducerService $producerService = null;

    /**
     * Publish a message using a producer class
     * Ensures it runs in a coroutine context
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param string|null $pool Optional connection pool name
     * @return bool Success state
     */
    public static function publish(string $producerClass, array $args = [], ?string $pool = null): bool
    {
        // Check if we're in a coroutine context
        if (!Coroutine::getCid()) {
            // If not, create a coroutine
            $result = [false];
            Coroutine::create(function () use ($producerClass, $args, $pool, &$result) {
                $result[0] = self::getProducerService()->produce($producerClass, $args, $pool);
            });
            return $result[0];
        }

        // Already in a coroutine
        return self::getProducerService()->produce($producerClass, $args, $pool);
    }

    /**
     * Get the producer service
     */
    private static function getProducerService(): ProducerService
    {
        if (self::$producerService === null) {
            // Use container to resolve dependencies
            global $container;

            if (isset($container) && method_exists($container, 'get')) {
                self::$producerService = $container->get(ProducerService::class);
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
     * Ensures it runs in a coroutine context
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param int $delayMs Delay in milliseconds
     * @param string|null $pool Optional connection pool name
     * @return bool Success state
     */
    public static function publishDelayed(string $producerClass, array $args = [], int $delayMs = 1000, ?string $pool = null): bool
    {
        // Check if we're in a coroutine context
        if (!Coroutine::getCid()) {
            // If not, create a coroutine
            $result = [false];
            Coroutine::create(function () use ($producerClass, $args, $delayMs, $pool, &$result) {
                $result[0] = self::getProducerService()->produceWithDelay($producerClass, $args, $delayMs, $pool);
            });
            return $result[0];
        }

        // Already in a coroutine
        return self::getProducerService()->produceWithDelay($producerClass, $args, $delayMs, $pool);
    }

    /**
     * Set the producer service (for testing or customization)
     */
    public static function setProducerService(ProducerService $service): void
    {
        self::$producerService = $service;
    }

    /**
     * Create a direct connection bypassing the pool
     */
    public static function createConnection(string $poolName = 'default'): AMQPStreamConnection
    {
        $config = config('amqp');
        $poolConfig = $config[$poolName] ?? $config['default'] ?? [];

        return new AMQPStreamConnection(
            host: $poolConfig['host'] ?? 'localhost',
            port: $poolConfig['port'] ?? 5672,
            user: $poolConfig['user'] ?? 'admin',
            password: $poolConfig['password'] ?? 'password',
            vhost: $poolConfig['vhost'] ?? '/',
            insist: ($poolConfig['params']['insist'] ?? false),
            login_method: ($poolConfig['params']['login_method'] ?? 'AMQPLAIN'),
            login_response: null,
            locale: ($poolConfig['params']['locale'] ?? 'en_US'),
            connection_timeout: ($poolConfig['params']['connection_timeout'] ?? 3.0),
            read_write_timeout: ($poolConfig['params']['read_write_timeout'] ?? 3.0),
            context: null,
            keepalive: ($poolConfig['params']['keepalive'] ?? false),
            heartbeat: ($poolConfig['params']['heartbeat'] ?? 0)
        );
    }
}