<?php

declare(strict_types=1);

namespace Ody\AMQP;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Throwable;

/**
 * Service for producing AMQP messages
 */
class ProducerService
{
    /**
     * @var AMQPManager AMQP Manager instance
     */
    private AMQPManager $amqpManager;

    /**
     * @var string Default connection name
     */
    private string $defaultConnectionName;

    /**
     * Constructor
     */
    public function __construct(
        AMQPManager $amqpManager,
        private LoggerInterface $logger,
        string      $defaultConnectionName = 'default'
    )
    {
        $this->amqpManager = $amqpManager;
        $this->defaultConnectionName = $defaultConnectionName;
    }

    /**
     * Produce a delayed message
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param int $delayMs Delay in milliseconds
     * @param string|null $connectionName Optional connection name
     * @return bool Success state
     */
    public function produceWithDelay(string $producerClass, array $args = [], int $delayMs = 1000, ?string $connectionName = null): bool
    {
        // Use default connection if not specified
        $connectionName ??= $this->defaultConnectionName;

        // Create producer instance
        $reflection = new ReflectionClass($producerClass);
        $producer = $reflection->newInstanceArgs($args);

        // Set delay
        $producer->setDelayMs($delayMs);

        // Produce the message
        return $this->amqpManager->produce($producer, $connectionName);
    }

    /**
     * Produce a message using a producer class
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param string|null $connectionName Optional connection name
     * @return bool Success state
     */
    public function produce(string $producerClass, array $args = [], ?string $connectionName = null): bool
    {
        // Use default connection if not specified
        $connectionName ??= $this->defaultConnectionName;

        try {
            // Verify that the producer class exists and has the Producer attribute
            if (!class_exists($producerClass)) {
                throw new InvalidArgumentException("Producer class $producerClass does not exist");
            }

            // Create producer instance with the provided arguments
            $reflection = new ReflectionClass($producerClass);
            $producer = $reflection->newInstanceArgs($args);

            // Pass to message processor
            return $this->amqpManager->produce($producer, $connectionName);
        } catch (Throwable $e) {
            // Log error
            $this->logger->error("Error producing message: " . $e->getMessage());
            return false;
        }
    }
}