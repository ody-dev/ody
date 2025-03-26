<?php

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Process\ProcessManager;
use Ody\Support\Config;
use Ody\Task\TaskManager;
use ReflectionAttribute;
use ReflectionClass;

class AMQPBootstrap
{
    private MessageProcessor $messageProcessor;
    private AMQPManager $amqpManager;

    public function __construct(
        private Config $config,
        private TaskManager     $taskManager,
        private ProcessManager  $processManager,
    )
    {
        $this->messageProcessor = new MessageProcessor($this->taskManager);
        $this->amqpManager = new AMQPManager(
            $this->messageProcessor,
            $this->taskManager,
            $this->processManager
        );
    }

    /**
     * Boot the AMQP module
     * This now only registers the configuration and starts consumer processes,
     * without initializing the connections yet.
     */
    public function boot(): void
    {
        // Check if AMQP is enabled
        $config = $this->config->get('amqp', []);
        if (!($config['enable'] ?? false)) {
            return;
        }

        // Store connection configurations for lazy initialization
        foreach ($config as $name => $poolConfig) {
            if (!is_array($poolConfig)) continue;
            if ($name === 'enable' || $name === 'producer' || $name === 'consumer' || $name === 'process' || $name === 'broker') continue;

            // Instead of initializing pools now, store the configuration
            ConnectionManager::storePoolConfig($poolConfig, $name);
        }

        // Register consumers and producers
        $this->registerComponents();

        // Start consumer processes based on configuration
        $processConfig = $config['process'] ?? [];
        if ($processConfig['enable'] ?? true) {
            $this->startConsumerProcesses($config);
        }
    }

    private function registerComponents(): void
    {
        // Register consumer classes
        $consumerClasses = $this->findConsumerClasses();
        foreach ($consumerClasses as $class) {
            try {
                // We can instantiate consumers as they likely don't require parameters
                $this->messageProcessor->registerConsumer(new $class());
            } catch (\Throwable $e) {
                // Log error but continue with other consumers
                error_log("Error instantiating consumer $class: " . $e->getMessage());
            }
        }

        // Register producer classes - but only register the class names, not instances
        $producerClasses = $this->findProducerClasses();
        foreach ($producerClasses as $class) {
            try {
                // Instead of creating an instance, just register the class information
                $this->messageProcessor->registerProducerClass($class);
            } catch (\Throwable $e) {
                // Log error but continue with other producers
                error_log("Error registering producer $class: " . $e->getMessage());
            }
        }
    }

    /**
     * Start consumer processes
     */
    private function startConsumerProcesses(array $config): void
    {
        $poolName = 'default';
        $maxConsumers = $config['process']['max_consumers'] ?? 10;
        $autoRestart = $config['process']['auto_restart'] ?? true;

        $consumers = $this->findConsumerClasses();
        foreach ($consumers as $consumerClass) {
            try {
                $reflection = new ReflectionClass($consumerClass);
                $attributes = $reflection->getAttributes(Consumer::class, ReflectionAttribute::IS_INSTANCEOF);

                if (empty($attributes)) {
                    continue;
                }

                $consumerInstance = new $consumerClass();
                $consumerAttribute = $attributes[0]->newInstance();

                // Skip if consumer is disabled
                if (!$consumerAttribute->enable) {
                    continue;
                }

                // Start the consumer processes
                $this->amqpManager->startConsumerProcess($consumerInstance, $consumerAttribute, $poolName);
            } catch (\Throwable $e) {
                // Log error but continue with other consumers
                error_log("Error starting consumer process for $consumerClass: " . $e->getMessage());
            }
        }
    }

    /**
     * Find consumer classes by scanning the configured directories
     */
    private function findConsumerClasses(): array
    {
        $config = $this->config->get('amqp', []);
        $paths = $config['consumer']['paths'] ?? ['app/Consumers'];

        // Map paths to absolute paths
        $absolutePaths = array_map(function ($path) {
            return $this->resolveBasePath($path);
        }, $paths);

        return ClassScanner::findConsumerClasses($absolutePaths);
    }

    /**
     * Find producer classes by scanning the configured directories
     */
    private function findProducerClasses(): array
    {
        $config = $this->config->get('amqp', []);
        $paths = $config['producer']['paths'] ?? ['app/Producers'];

        // Map paths to absolute paths
        $absolutePaths = array_map(function ($path) {
            return $this->resolveBasePath($path);
        }, $paths);

        return ClassScanner::findProducerClasses($absolutePaths);
    }

    /**
     * Resolve a path relative to the application base path
     */
    private function resolveBasePath(string $path): string
    {
        // If path is already absolute, return it
        if (strpos($path, '/') === 0) {
            return $path;
        }

        // Get base path from config if available
        $basePath = $this->config->get('app.base_path', getcwd());

        return rtrim($basePath, '/') . '/' . $path;
    }
}