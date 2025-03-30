<?php

namespace Ody\AMQP\Providers;

use Ody\AMQP\AMQPBootstrap;
use Ody\AMQP\AMQPChannelPool;
use Ody\AMQP\AMQPClient;
use Ody\AMQP\AMQPConnectionPool;
use Ody\AMQP\AMQPManager;
use Ody\AMQP\ConnectionFactory;
use Ody\AMQP\MessageProcessor;
use Ody\AMQP\PooledAMQPManager;
use Ody\AMQP\PooledMessageProcessor;
use Ody\AMQP\ProducerService;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Process\ProcessManager;
use Ody\Support\Config;
use Ody\Task\TaskManager;
use Psr\Log\LoggerInterface;

class AMQPServiceProvider extends ServiceProvider
{
    /**
     * Register the AMQP services.
     */
    public function register(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Register core AMQP services
        $this->registerServices();
    }

    /**
     * Register core AMQP services
     */
    private function registerServices(): void
    {
        // Register the connection factory
        $this->container->singleton(ConnectionFactory::class, function () {
            return new ConnectionFactory(
                $this->container->get(Config::class)
            );
        });

        // Register the connection pool
        $this->container->singleton(AMQPConnectionPool::class, function () {
            $pool = new AMQPConnectionPool(
                $this->container->get(Config::class),
                $this->container->get(ConnectionFactory::class),
                $this->container->make(LoggerInterface::class)
            );

            // Configure the pool based on config
            $config = $this->container->get(Config::class)->get('amqp.pool', []);
            if (isset($config['max_connections'])) {
                $pool->setMaxPoolSize($config['max_connections']);
            }
            if (isset($config['max_idle_time'])) {
                $pool->setMaxIdleTime($config['max_idle_time']);
            }

            return $pool;
        });

        // Register the channel pool
        $this->container->singleton(AMQPChannelPool::class, function () {
            $pool = new AMQPChannelPool(
                $this->container->get(AMQPConnectionPool::class),
                $this->container->get(Config::class),
                $this->container->make(LoggerInterface::class)
            );

            // Configure the pool based on config
            $config = $this->container->get(Config::class)->get('amqp.pool', []);
            if (isset($config['max_channels_per_connection'])) {
                $pool->setMaxChannelsPerConnection($config['max_channels_per_connection']);
            }

            return $pool;
        });

        // Register the bootstrap
        $this->container->singleton(AMQPBootstrap::class, function () {
            return new AMQPBootstrap(
                $this->container->get(Config::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class),
                $this->container->get(ConnectionFactory::class),
                $this->container->make(LoggerInterface::class)
            );
        });

        // Register the message processor
        $this->container->singleton(MessageProcessor::class, function () {
            return new MessageProcessor($this->container->get(TaskManager::class));
        });

        // Register pooled message processor
        $this->container->singleton(PooledMessageProcessor::class, function () {
            return new PooledMessageProcessor(
                $this->container->get(TaskManager::class),
                $this->container->get(AMQPChannelPool::class)
            );
        });

        // Register the AMQPManager
        $this->container->singleton(AMQPManager::class, function () {
            return new AMQPManager(
                $this->container->get(MessageProcessor::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class),
                $this->container->get(ConnectionFactory::class),
                $this->container->make(LoggerInterface::class)
            );
        });

        // Register pooled AMQPManager
        $this->container->singleton(PooledAMQPManager::class, function () {
            return new PooledAMQPManager(
                $this->container->get(PooledMessageProcessor::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class),
                $this->container->get(ConnectionFactory::class),
                $this->container->make(LoggerInterface::class)
            );
        });

        // Register the producer service
        $this->container->singleton(ProducerService::class, function () {
            // Use the pooled manager if connection pooling is enabled
            $config = $this->container->get(Config::class)->get('amqp', []);
            $usePooling = $config['pool']['enable'] ?? true;

            if ($usePooling) {
                return new ProducerService(
                    $this->container->get(PooledAMQPManager::class),
                    $this->container->make(LoggerInterface::class)
                );
            }

            return new ProducerService(
                $this->container->get(AMQPManager::class),
                $this->container->make(LoggerInterface::class)
            );
        });

        // Register the AMQPClient (replacement for static AMQP facade)
        $this->container->singleton(AMQPClient::class, function () {
            return new AMQPClient(
                $this->container->get(ProducerService::class),
                $this->container->get(AMQPConnectionPool::class),
                $this->container->get(AMQPChannelPool::class)
            );
        });
    }

    /**
     * Bootstrap the AMQP services.
     * This gets called during the application bootstrapping phase, before the server starts.
     */
    public function boot(): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        // Initialize the AMQPBootstrap
        $this->container->get(AMQPBootstrap::class)->boot();

        // Listen for server shutdown event to close all connections
        $this->listenForShutdown();
    }

    /**
     * Register a listener for server shutdown events to clean up connections
     */
    private function listenForShutdown(): void
    {
        // Alternative: Register directly with Swoole server if available
        try {
            /** @var \Swoole\Server|null $server */
            $server = $this->container->make('swoole.server');

            if ($server) {
                $server->on('shutdown', function () {
                    logger()->debug("[AMQP] Swoole server shutting down, closing all pooled connections");
                    $this->container->get(AMQPConnectionPool::class)->closeAll();
                    $this->container->get(AMQPChannelPool::class)->closeAll();
                });
            }
        } catch (\Throwable $e) {
            // Server might not be available yet, that's fine
        }
    }
}