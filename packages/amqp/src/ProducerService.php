<?php

declare(strict_types=1);

namespace Ody\AMQP;

use ReflectionClass;

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
     * @var string Default connection pool name
     */
    private string $defaultPool;

    /**
     * Constructor
     */
    public function __construct(AMQPManager $amqpManager, string $defaultPool = 'default')
    {
        $this->amqpManager = $amqpManager;
        $this->defaultPool = $defaultPool;
    }

    /**
     * Produce a delayed message
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param int $delayMs Delay in milliseconds
     * @param string|null $poolName Optional connection pool name
     * @return bool Success state
     */
    public function produceWithDelay(string $producerClass, array $args = [], int $delayMs = 1000, ?string $poolName = null): bool
    {
        // Use default pool if not specified
        $poolName ??= $this->defaultPool;

        // Create producer instance
        $reflection = new ReflectionClass($producerClass);
        $producer = $reflection->newInstanceArgs($args);

        // Set delay
        $producer->setDelayMs($delayMs);

        // Produce the message
        return $this->amqpManager->produce($producer, $poolName);
    }

    /**
     * Produce a message using a producer class
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param string|null $poolName Optional connection pool name
     * @return bool Success state
     */
    public function produce(string $producerClass, array $args = [], ?string $poolName = null): bool
    {
        // Use default pool if not specified
        $poolName ??= $this->defaultPool;

        try {
            // Verify that the producer class exists and has the Producer attribute
            if (!class_exists($producerClass)) {
                throw new \InvalidArgumentException("Producer class $producerClass does not exist");
            }

            // Create producer instance with the provided arguments
            $reflection = new ReflectionClass($producerClass);
            $producer = $reflection->newInstanceArgs($args);

            // Pass to message processor - let it decide if a coroutine is needed
            return $this->amqpManager->produce($producer, $poolName);
        } catch (\Throwable $e) {
            // Log error
            error_log("Error producing message: " . $e->getMessage());
            return false;
        }
    }
}