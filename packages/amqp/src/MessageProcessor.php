<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Attributes\Producer;
use Ody\Task\TaskManager;
use ReflectionAttribute;
use ReflectionClass;

class MessageProcessor
{
    /**
     * @var array<string, array>
     */
    private array $consumers = [];

    /**
     * @var array<string, array>
     */
    private array $producers = [];

    /**
     * @var array<string, Producer> Store producer class attributes without instantiating
     */
    protected array $producerClasses = [];

    /**
     * @var array<string, Consumer> Store consumer class attributes without instantiating
     */
    private array $consumerClasses = [];

    /**
     * @var TaskManager The task manager
     */
    private TaskManager $taskManager;

    /**
     * Constructor
     */
    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function registerConsumerClass(string $consumerClass): void
    {
        if (!class_exists($consumerClass)) {
            throw new \InvalidArgumentException("Consumer class $consumerClass does not exist");
        }

        $reflection = new ReflectionClass($consumerClass);
        $attributes = $reflection->getAttributes(Consumer::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            throw new \InvalidArgumentException("Class $consumerClass does not have the Consumer attribute");
        }

        $consumerAttribute = $attributes[0]->newInstance();
        $this->consumerClasses[$consumerClass] = $consumerAttribute;
    }

    /**
     * Register a producer class without instantiating it
     * This is useful for producers that require constructor parameters
     */
    public function registerProducerClass(string $producerClass): void
    {
        if (!class_exists($producerClass)) {
            throw new \InvalidArgumentException("Producer class $producerClass does not exist");
        }

        $reflection = new ReflectionClass($producerClass);
        $attributes = $reflection->getAttributes(Producer::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            throw new \InvalidArgumentException("Class $producerClass does not have the Producer attribute");
        }

        $producerAttribute = $attributes[0]->newInstance();
        $this->producerClasses[$producerClass] = $producerAttribute;
    }

    /**
     * Produce a message
     * This method now ensures it's running in a coroutine
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        return false;
    }
}