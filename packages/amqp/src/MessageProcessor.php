<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Attributes\Producer;
use Ody\Task\TaskManager;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionAttribute;
use ReflectionClass;
use Swoole\Coroutine;

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
    private array $producerClasses = [];

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

    /**
     * Register a consumer
     */
    public function registerConsumer(object $consumer): void
    {
        $reflection = new ReflectionClass($consumer);
        $attributes = $reflection->getAttributes(Consumer::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return;
        }

        $consumerAttribute = $attributes[0]->newInstance();
        $this->consumers[$reflection->getName()] = [
            'instance' => $consumer,
            'attribute' => $consumerAttribute,
        ];
    }

    // Add this method to MessageProcessor
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
     * Register a producer
     */
    public function registerProducer(object $producer): void
    {
        $reflection = new ReflectionClass($producer);
        $attributes = $reflection->getAttributes(Producer::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return;
        }

        $producerAttribute = $attributes[0]->newInstance();
        $this->producers[$reflection->getName()] = [
            'instance' => $producer,
            'attribute' => $producerAttribute,
        ];
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
     * Get all registered consumers
     */
    public function getConsumers(): array
    {
        return $this->consumers;
    }

    /**
     * Get all registered producers
     */
    public function getProducers(): array
    {
        return $this->producers;
    }

    /**
     * Get all registered producer classes
     */
    public function getProducerClasses(): array
    {
        return $this->producerClasses;
    }

    /**
     * Get producer attribute for a class
     */
    public function getProducerAttribute(string $producerClass): ?Producer
    {
        return $this->producerClasses[$producerClass] ?? null;
    }

    /**
     * Produce a message
     * This method now ensures it's running in a coroutine
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        // Check if already in a coroutine
        if (!Coroutine::getCid()) {
            // If not in a coroutine, create one
            $result = [false];
            Coroutine\run(function () use ($producerMessage, $connectionName, &$result) {
                $result[0] = $this->doProduceInCoroutine($producerMessage, $connectionName);
            });

            return $result[0];
        }

        // Already in a coroutine
        return $this->doProduceInCoroutine($producerMessage, $connectionName);
    }

    private function doProduceInCoroutine(object $producerMessage, string $connectionName): bool
    {
        $connection = null;
        $channel = null;

        try {
            // Create a direct connection
            $connection = AMQP::createConnection($connectionName);
            $channel = $connection->channel();

            // Get producer attribute
            $producerAttribute = null;
            $reflection = new ReflectionClass($producerMessage);
            $className = $reflection->getName();

            // First check if we have a pre-registered attribute for this class
            if (isset($this->producerClasses[$className])) {
                $producerAttribute = $this->producerClasses[$className];
            } else {
                // Otherwise, try to get it from the object
                $attributes = $reflection->getAttributes(Producer::class, ReflectionAttribute::IS_INSTANCEOF);
                if (!empty($attributes)) {
                    $producerAttribute = $attributes[0]->newInstance();
                }
            }

            if (!$producerAttribute) {
                throw new \RuntimeException("Message class must have Producer attribute");
            }

            // Declare exchange
            $channel->exchange_declare(
                $producerAttribute->exchange,
                $producerAttribute->type,
                false,
                true,
                false
            );

            // Get routing key from attribute or method if specified
            $routingKey = $producerAttribute->routingKey;

            // Check if the message has a specific routing key
            $methodAttributes = $reflection->getMethod('__construct')->getAttributes(Attributes\ProduceMessage::class);
            if (!empty($methodAttributes)) {
                $methodAttr = $methodAttributes[0]->newInstance();
                if ($methodAttr->routingKey !== null) {
                    $routingKey = $methodAttr->routingKey;
                }
            }

            // Create and publish message
            $payload = $producerMessage->getPayload();
            $properties = $producerMessage->getProperties();

            // Set content type to JSON if not specified
            if (!isset($properties['content_type'])) {
                $properties['content_type'] = 'application/json';
            }

            $message = new AMQPMessage(
                json_encode($payload),
                $properties
            );

            $channel->basic_publish(
                $message,
                $producerAttribute->exchange,
                $routingKey
            );

            // Close the channel and connection
            $channel->close();
            $connection->close();

            return true;
        } catch (\Throwable $e) {
            // Log the error
            error_log("Error producing AMQP message: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // Clean up
            try {
                if (isset($channel) && $channel->is_open()) {
                    $channel->close();
                }

                if (isset($connection) && $connection->isConnected()) {
                    $connection->close();
                }
            } catch (\Throwable $cleanup) {
                // Ignore cleanup errors
            }

            return false;
        }
    }
}