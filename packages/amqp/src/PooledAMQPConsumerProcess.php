<?php

namespace Ody\AMQP\Providers;

use Ody\AMQP\AMQPChannelPool;
use Ody\AMQP\AMQPConnectionPool;
use Ody\AMQP\AMQPConsumerProcess;
use PhpAmqpLib\Message\AMQPMessage;
use Swoole\Timer;

/**
 * Modified AMQPConsumerProcess with connection pooling
 */
class PooledAMQPConsumerProcess extends AMQPConsumerProcess
{
    /**
     * Set up the AMQP connection and channel using pooling
     */
    protected function setupConnection(): void
    {
        try {
            error_log("[AMQP] Setting up pooled connection for {$this->consumerAttribute->queue}");

            // Create the consumer instance
            $consumer = new $this->consumerClass();

            // Get pooled connection
            $this->connection = AMQPConnectionPool::getConnection($this->connectionName);

            error_log("[AMQP] Pooled connection established, creating channel");
            $this->channel = AMQPChannelPool::getChannel($this->connectionName);

            // Set QoS if specified
            $prefetchCount = $this->consumerAttribute->prefetchCount ?? 10;
            error_log("[AMQP] Setting prefetch count to {$prefetchCount}");
            $this->channel->basic_qos(0, $prefetchCount, false);

            // Declare exchange
            error_log("[AMQP] Declaring exchange {$this->consumerAttribute->exchange}");
            $this->channel->exchange_declare(
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->type,
                false,
                true,
                false
            );

            // Declare queue
            error_log("[AMQP] Declaring queue {$this->consumerAttribute->queue}");
            $this->channel->queue_declare(
                $this->consumerAttribute->queue,
                false,
                true,
                false,
                false
            );

            // Bind queue to exchange
            error_log("[AMQP] Binding queue to exchange with routing key {$this->consumerAttribute->routingKey}");
            $this->channel->queue_bind(
                $this->consumerAttribute->queue,
                $this->consumerAttribute->exchange,
                $this->consumerAttribute->routingKey
            );

            // Set up consumer callback
            error_log("[AMQP] Setting up consumer callback");
            $this->channel->basic_consume(
                $this->consumerAttribute->queue,
                '', // consumer tag
                false, // no local
                false, // no ack
                false, // exclusive
                false, // no wait
                function (AMQPMessage $message) use ($consumer) {
                    // Process the message
                    $this->processAmqpMessage($consumer, $message);
                }
            );

            logger()->error("[AMQP] Consumer setup complete for {$this->consumerAttribute->queue}");
            $this->reconnecting = false;
            $this->reconnectAttempts = 0;
            $this->lastActivityTime = time(); // Reset activity time

        } catch (\Throwable $e) {
            logger()->error("[AMQP] Error during connection setup: " . $e->getMessage());
            logger()->error($e->getTraceAsString());

            // Clean up any partial resources
            $this->cleanupResources();

            // Schedule reconnect if allowed
            $this->handleDisconnect();
        }
    }

    /**
     * Clean up resources without actually closing pooled connections
     */
    protected function cleanupResources(bool $clearTimers = true): void
    {
        // Only clear timers and reset local variables
        // Don't actually close the connections since they're pooled

        $this->channel = null;
        $this->connection = null;

        // Clear timers if requested
        if ($clearTimers && $this->heartbeatTimerId !== null) {
            Timer::clear((int)$this->heartbeatTimerId);
            $this->heartbeatTimerId = null;
        }
    }
}