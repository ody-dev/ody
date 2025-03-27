<?php

namespace Ody\AMQP\Providers;

use Ody\AMQP\AMQP;
use Ody\AMQP\AMQPBootstrap;
use Ody\AMQP\AMQPChannelPool;
use Ody\AMQP\AMQPConnectionPool;
use Ody\AMQP\AMQPManager;
use Ody\AMQP\MessageProcessor;
use Ody\AMQP\PooledAMQPManager;
use Ody\AMQP\PooledMessageProcessor;
use Ody\AMQP\ProducerService;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Process\ProcessManager;
use Ody\Support\Config;
use Ody\Task\TaskManager;
use Swoole\Server;

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

        // Register connection pooling
        $this->registerConnectionPooling();
    }

    /**
     * Register core AMQP services
     */
    private function registerServices(): void
    {
        $this->container->singleton(AMQPBootstrap::class, function () {
            return new AMQPBootstrap(
                $this->container->get(Config::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class)
            );
        });

        // Register the message processor
        $this->container->singleton(MessageProcessor::class, function () {
            return new MessageProcessor($this->container->get(TaskManager::class));
        });

        // Register pooled message processor as alternative
        $this->container->singleton(PooledMessageProcessor::class, function () {
            return new PooledMessageProcessor($this->container->get(TaskManager::class));
        });

        // Register the AMQPManager
        $this->container->singleton(AMQPManager::class, function () {
            return new AMQPManager(
                $this->container->get(MessageProcessor::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class)
            );
        });

        // Register pooled AMQPManager as alternative
        $this->container->singleton(PooledAMQPManager::class, function () {
            return new PooledAMQPManager(
                $this->container->get(PooledMessageProcessor::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class)
            );
        });

        // Register the producer service
        $this->container->singleton(ProducerService::class, function () {
            // Use the pooled manager if connection pooling is enabled
            $config = $this->container->get(Config::class)->get('amqp', []);
            $usePooling = $config['pool']['enable'] ?? true;

            if ($usePooling) {
                return new ProducerService(
                    $this->container->get(PooledAMQPManager::class)
                );
            }

            return new ProducerService(
                $this->container->get(AMQPManager::class)
            );
        });

        // Register the container with the AMQP static class
        AMQP::setContainer($this->container);
    }

    /**
     * Register connection pooling services
     */
    private function registerConnectionPooling(): void
    {
        $config = $this->container->get(Config::class)->get('amqp', []);
        $poolConfig = $config['pool'] ?? [];

        // Only set up pooling if enabled
        if (!($poolConfig['enable'] ?? true)) {
            return;
        }

        // Configure the connection pool
        AMQPConnectionPool::setMaxPoolSize($poolConfig['max_connections'] ?? 10);
        AMQPConnectionPool::setMaxIdleTime($poolConfig['max_idle_time'] ?? 60);
        AMQPChannelPool::setMaxChannelsPerConnection($poolConfig['max_channels_per_connection'] ?? 10);

        // Log that the pool is configured
        error_log("[AMQP] Connection pool registered and configured");
    }

    /**
     * Bootstrap the AMQP services.
     * This gets called during the application bootstrapping phase, before the server starts.
     */
    public function boot(): void
    {
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
        if ($this->isRunningInConsole()) {
            return;
        }
        // Register a callback for when the server is shutting down
        // This helps ensure proper cleanup of pooled connections
//        $this->container->make('events')->listen('swoole.shutdown', function () {
//            error_log("[AMQP] Server shutting down, closing all pooled connections");
//            AMQPConnectionPool::closeAll();
//            AMQPChannelPool::closeAll();
//        });

        // Alternative: Register directly with Swoole server if available
        try {
            /** @var Server|null $server */
            $server = $this->container->make('swoole.server');

            if ($server) {
                $server->on('shutdown', function () {
                    error_log("[AMQP] Swoole server shutting down, closing all pooled connections");
                    AMQPConnectionPool::closeAll();
                    AMQPChannelPool::closeAll();
                });
            }
        } catch (\Throwable $e) {
            // Server might not be available yet, that's fine
        }
    }
}