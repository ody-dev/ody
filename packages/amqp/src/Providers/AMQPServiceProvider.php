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
    public function register(): void
    {
        // Register the AMQP services.
        $this->registerServices();
    }

    private function registerServices(): void
    {
        $this->container->singleton(ConnectionFactory::class, fn() => new ConnectionFactory(
            $this->container->get(Config::class)
        ));

        $this->container->singleton(AMQPConnectionPool::class, function () {
            $pool = new AMQPConnectionPool(
                $this->container->get(Config::class),
                $this->container->get(ConnectionFactory::class),
                $this->container->make(LoggerInterface::class)
            );

            $config = $this->container->get(Config::class)->get('amqp.pool', []);
            if (isset($config['max_connections'])) {
                $pool->setMaxPoolSize($config['max_connections']);
            }
            if (isset($config['max_idle_time'])) {
                $pool->setMaxIdleTime($config['max_idle_time']);
            }

            return $pool;
        });

        $this->container->singleton(AMQPChannelPool::class, function () {
            $pool = new AMQPChannelPool(
                $this->container->get(AMQPConnectionPool::class),
                $this->container->get(Config::class),
                $this->container->make(LoggerInterface::class)
            );

            $config = $this->container->get(Config::class)->get('amqp.pool', []);
            if (isset($config['max_channels_per_connection'])) {
                $pool->setMaxChannelsPerConnection($config['max_channels_per_connection']);
            }

            return $pool;
        });

        $this->container->singleton(AMQPBootstrap::class, fn() => new AMQPBootstrap(
            $this->container->get(Config::class),
            $this->container->get(TaskManager::class),
            $this->container->get(ProcessManager::class),
            $this->container->get(ConnectionFactory::class),
            $this->container->make(LoggerInterface::class),
            $this->container
        ));

        $this->container->singleton(MessageProcessor::class, fn() => new MessageProcessor(
            $this->container->get(TaskManager::class)
        ));

        $this->container->singleton(PooledMessageProcessor::class, fn() => new PooledMessageProcessor(
            $this->container->get(TaskManager::class),
            $this->container->get(AMQPChannelPool::class)
        ));

        $this->container->singleton(AMQPManager::class, fn() => new AMQPManager(
            $this->container->get(MessageProcessor::class),
            $this->container->get(TaskManager::class),
            $this->container->get(ProcessManager::class),
            $this->container->get(ConnectionFactory::class),
            $this->container->make(LoggerInterface::class),
            $this->container
        ));

        $this->container->singleton(PooledAMQPManager::class, fn() => new PooledAMQPManager(
            $this->container->get(PooledMessageProcessor::class),
            $this->container->get(TaskManager::class),
            $this->container,
            $this->container->get(ProcessManager::class),
            $this->container->get(ConnectionFactory::class),
            $this->container->make(LoggerInterface::class),
        ));

        $this->container->singleton(ProducerService::class, function () {
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

        $this->container->singleton(AMQPClient::class, fn() => new AMQPClient(
            $this->container->get(ProducerService::class),
            $this->container->get(AMQPConnectionPool::class),
            $this->container->get(AMQPChannelPool::class)
        ));
    }

    public function boot(): void
    {
        // Initialize the AMQPBootstrap
        $this->container->get(AMQPBootstrap::class)->boot();

        // Bootstrap the AMQP services.
        // Listen for server shutdown event to close all connections
        $this->listenForShutdown();
    }

    private function listenForShutdown(): void
    {
        try {
            /** @var \Swoole\Server|null $server */
            $server = $this->container->make('swoole.server');

            $server?->on('shutdown', function () {
                logger()->debug("[AMQP] Swoole server shutting down, closing all pooled connections");
                $this->container->get(AMQPConnectionPool::class)->closeAll();
                $this->container->get(AMQPChannelPool::class)->closeAll();
            });
        } catch (\Throwable $e) {
            // Server might not be available yet, that's fine
        }
    }
}