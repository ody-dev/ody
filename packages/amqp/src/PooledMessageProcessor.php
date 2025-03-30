<?php

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Producer;
use Ody\Task\TaskManager;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionClass;

/**
 * Modified MessageProcessor with connection and channel pooling
 */
class PooledMessageProcessor extends MessageProcessor
{
    /**
     * Constructor to inject dependencies
     */
    public function __construct(
        TaskManager             $taskManager,
        private AMQPChannelPool $channelPool
    )
    {
        parent::__construct($taskManager);
    }

    /**
     * Produce a message with connection pooling
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
        // Get a pooled channel for this connection
        $channel = $this->channelPool->getChannel($connectionName);

        try {
            // Get producer attribute
            $producerAttribute = null;
            $reflection = new ReflectionClass($producerMessage);
            $className = $reflection->getName();

            // First check if we have a pre-registered attribute for this class
            if (isset($this->producerClasses[$className])) {
                $producerAttribute = $this->producerClasses[$className];
            } else {
                // Otherwise, try to get it from the object
                $attributes = $reflection->getAttributes(Producer::class, \ReflectionAttribute::IS_INSTANCEOF);
                if (!empty($attributes)) {
                    $producerAttribute = $attributes[0]->newInstance();
                }
            }

            if (!$producerAttribute) {
                throw new \RuntimeException("Message class must have Producer attribute");
            }

            // Declare exchange if needed (idempotent operation in RabbitMQ)
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

            // Do NOT close the channel - it will be returned to the pool

            return true;
        } catch (\Throwable $e) {
            // Log the error
            logger()->error("Error producing AMQP message: " . $e->getMessage());
            logger()->error($e->getTraceAsString());

            // Clean up
            try {
                if ($channel->is_open()) {
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