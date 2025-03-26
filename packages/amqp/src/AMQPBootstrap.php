<?php

namespace Ody\AMQP;

use Ody\Config\ConfigInterface;
use Ody\Process\ProcessManager;
use Ody\Task\TaskManager;

class AMQPBootstrap
{
    private MessageProcessor $messageProcessor;

    public function __construct(
        private ConfigInterface $config,
        private TaskManager     $taskManager,
        private ProcessManager  $processManager,
    )
    {
        $this->messageProcessor = new MessageProcessor($this->taskManager);
    }

    public function boot(): void
    {
        // Initialize connection pool
        $config = $this->config->get('amqp', []);
        if (!$config['enable'] ?? false) {
            return;
        }

        // Initialize the connection pool
        foreach ($config as $name => $poolConfig) {
            if ($name === 'enable') continue;
            ConnectionManager::initPool($poolConfig, $name);
        }

        // Register all consumers and producers (could use auto-discovery here)
        $this->registerComponents();

        // Start consumer processes
        $this->messageProcessor->startConsumers($config);
    }

    private function registerComponents(): void
    {
        // This could scan directories or use a service registry
        // For now, manual registration as an example
        $consumerClasses = $this->findConsumerClasses();
        foreach ($consumerClasses as $class) {
            $this->messageProcessor->registerConsumer(new $class());
        }

        $producerClasses = $this->findProducerClasses();
        foreach ($producerClasses as $class) {
            $this->messageProcessor->registerProducer(new $class());
        }
    }

    // Implementation of class discovery methods...
}