<?php

declare(strict_types=1);

namespace Ody\AMQP;

/**
 * Client for interacting with AMQP services using dependency injection
 * This replaces the static AMQP facade
 */
class AMQPClient
{
    /**
     * Constructor with dependencies
     */
    public function __construct(
        private ProducerService    $producerService,
        private AMQPConnectionPool $connectionPool,
        private AMQPChannelPool    $channelPool
    )
    {
    }

    /**
     * Publish a message using a producer class
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param string|null $connectionName Optional connection configuration name
     * @return bool Success state
     */
    public function publish(string $producerClass, array $args = [], ?string $connectionName = null): bool
    {
        return $this->producerService->produce($producerClass, $args, $connectionName);
    }

    /**
     * Publish a delayed message
     *
     * @param string $producerClass Producer class name
     * @param array $args Constructor arguments for the producer
     * @param int $delayMs Delay in milliseconds
     * @param string|null $connectionName Optional connection configuration name
     * @return bool Success state
     */
    public function publishDelayed(string $producerClass, array $args = [], int $delayMs = 1000, ?string $connectionName = null): bool
    {
        return $this->producerService->produceWithDelay($producerClass, $args, $delayMs, $connectionName);
    }

    /**
     * Get a pooled connection
     *
     * @param string $connectionName Connection configuration name
     * @return \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    public function getConnection(string $connectionName = 'default'): \PhpAmqpLib\Connection\AMQPStreamConnection
    {
        return $this->connectionPool->getConnection($connectionName);
    }

    /**
     * Get a pooled channel
     *
     * @param string $connectionName Connection configuration name
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel(string $connectionName = 'default'): \PhpAmqpLib\Channel\AMQPChannel
    {
        return $this->channelPool->getChannel($connectionName);
    }

    /**
     * Get connection pool statistics
     *
     * @return array
     */
    public function getConnectionStats(): array
    {
        return $this->connectionPool->getStats();
    }

    /**
     * Get channel pool statistics
     *
     * @return array
     */
    public function getChannelStats(): array
    {
        return $this->channelPool->getStats();
    }
}