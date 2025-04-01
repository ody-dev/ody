<?php

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\Container\Container;
use Ody\Process\ProcessManager;
use Ody\Support\Config;
use Ody\Task\TaskManager;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;

class AMQPBootstrap
{
    private MessageProcessor $messageProcessor;
    private AMQPManager $amqpManager;

    /**
     * Track which consumers have already been forked to prevent duplicates
     */
    private array $forkedConsumers = [];

    public function __construct(
        private Config $config,
        private TaskManager    $taskManager,
        private ProcessManager $processManager,
        private ConnectionFactory $connectionFactory,
        private LoggerInterface $logger,
        private Container $container
    )
    {
        $this->messageProcessor = new MessageProcessor($this->taskManager);
        $this->amqpManager = new AMQPManager(
            $this->messageProcessor,
            $this->taskManager,
            $this->processManager,
            $this->connectionFactory,
            $this->logger,
            $this->container
        );
    }

    /**
     * Boot the AMQP module
     * This needs to fork processes before the server starts
     */
    public function boot(): void
    {
        // Check if AMQP is enabled
        $config = $this->config->get('amqp', []);
        if (!($config['enable'] ?? false)) {
            return;
        }

        // Register components (but don't create instances yet)
        $this->registerComponents();

        // Start consumer processes based on configuration
        $processConfig = $config['process'] ?? [];
        if ($processConfig['enable'] ?? true) {
            $this->forkConsumerProcesses($config);
        }
    }

    private function registerComponents(): void
    {
        // Register consumer classes
        $consumerClasses = $this->findConsumerClasses();
        foreach ($consumerClasses as $class) {
            try {
                // Just register the class, don't instantiate yet
                $this->messageProcessor->registerConsumerClass($class);
            } catch (\Throwable $e) {
                // Log error but continue with other consumers
                $this->logger->error("Error registering consumer $class: " . $e->getMessage());
            }
        }

        // Register producer classes - but only register the class names, not instances
        $producerClasses = $this->findProducerClasses();
        foreach ($producerClasses as $class) {
            try {
                $this->messageProcessor->registerProducerClass($class);
            } catch (\Throwable $e) {
                // Log error but continue with other producers
                $this->logger->error("Error registering producer $class: " . $e->getMessage());
            }
        }
    }

    /**
     * Fork consumer processes now, but the actual consumer logic will wait
     * until server/worker is ready
     */
    private function forkConsumerProcesses(array $config): void
    {
        $connectionName = 'default';
        $consumerClasses = $this->findConsumerClasses();

        $this->logger->debug("[AMQP] Found " . count($consumerClasses) . " consumer classes");

        foreach ($consumerClasses as $consumerClass) {
            try {
                $reflection = new ReflectionClass($consumerClass);
                $attributes = $reflection->getAttributes(Consumer::class, ReflectionAttribute::IS_INSTANCEOF);

                if (empty($attributes)) {
                    continue;
                }

                $consumerAttribute = $attributes[0]->newInstance();

                // Skip if consumer is disabled
                if (!$consumerAttribute->enable) {
                    continue;
                }

                // Skip if we already forked a process for this queue
                $queueKey = $consumerAttribute->exchange . ':' . $consumerAttribute->queue;
                if (isset($this->forkedConsumers[$queueKey])) {
                    $this->logger->debug("[AMQP] Skipping duplicate consumer for queue {$consumerAttribute->queue}");
                    continue;
                }

                // Mark this queue as forked
                $this->forkedConsumers[$queueKey] = true;

                $this->logger->debug("[AMQP] Forking consumer process for {$consumerClass} on queue {$consumerAttribute->queue}");

                // Fork consumer process with necessary information
                $this->amqpManager->forkConsumerProcess($consumerClass, $consumerAttribute, $connectionName);
            } catch (\Throwable $e) {
                // Log error but continue with other consumers
                $this->logger->error("Error forking consumer process for $consumerClass: " . $e->getMessage());
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

        $classes = ClassScanner::findConsumerClasses($absolutePaths);

        // Remove duplicates by using the class name as the key
        return array_unique($classes);
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

        $classes = ClassScanner::findProducerClasses($absolutePaths);

        // Remove duplicates by using the class name as the key
        return array_unique($classes);
    }

    /**
     * Resolve a path relative to the application base path
     */
    private function resolveBasePath(string $path): string
    {
        // If path is already absolute, return it
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Get base path from config if available
        $basePath = $this->config->get('app.base_path', getcwd());

        return rtrim($basePath, '/') . '/' . $path;
    }
}