<?php

namespace Ody\AMQP\Providers;

use Ody\AMQP\AMQPBootstrap;
use Ody\AMQP\AMQPManager;
use Ody\AMQP\ProducerService;
use Ody\Foundation\Providers\ServiceProvider;
use Ody\Process\ProcessManager;
use Ody\Support\Config;
use Ody\Task\TaskManager;

class AMQPServiceProvider extends ServiceProvider
{
    /**
     * Register the AMQP services.
     */
    public function register(): void
    {
        $this->container->singleton(AMQPBootstrap::class, function () {
            return new AMQPBootstrap(
                $this->container->get(Config::class),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class)
            );
        });

        $this->container->singleton(AMQPManager::class, function () {
            return new AMQPManager(
                $this->container->get('amqp.message_processor'),
                $this->container->get(TaskManager::class),
                $this->container->get(ProcessManager::class)
            );
        });

        // Register the message processor as a singleton
        $this->container->singleton('amqp.message_processor', function () {
            return new \Ody\AMQP\MessageProcessor($this->container->get(TaskManager::class));
        });

        // Register the producer service
        $this->container->singleton(ProducerService::class, function () {
            return new ProducerService(
                $this->container->get(AMQPManager::class)
            );
        });
    }

    /**
     * Bootstrap the AMQP services.
     * This gets called during the application bootstrapping phase, before the server starts.
     */
    public function boot(): void
    {
        // Only bootstrap AMQP when running the server (not in CLI commands)
        if (!$this->isRunningInConsole() || $this->isServerCommand()) {
            // Initialize AMQP connections and processes
            $this->container->get(AMQPBootstrap::class)->boot();
        }
    }

    /**
     * Check if we're running a server command
     */
    protected function isServerCommand(): bool
    {
        global $argv;
        if (!isset($argv)) {
            return false;
        }

        $command = $argv[1] ?? '';
        return $command === 'serve' || $command === 'start' || $command === 'restart';
    }
}