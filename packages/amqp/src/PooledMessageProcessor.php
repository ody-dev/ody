<?php

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Producer;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionClass;

/**
 * Modified MessageProcessor with connection and channel pooling
 */
class PooledMessageProcessor extends MessageProcessor
{
    /**
     * Produce a message with connection pooling
     */
    public function produce(object $producerMessage, string $connectionName = 'default'): bool
    {
//        // Check if already in a coroutine
//        if (!Coroutine::getCid()) {
//            // If not in a coroutine, create one
//            $result = [false];
//            Coroutine\run(function () use ($producerMessage, $connectionName, &$result) {
//                $result[0] = $this->doProduceInCoroutine($producerMessage, $connectionName);
//            });
//
//            return $result[0];
//        }

        // Already in a coroutine
        return $this->doProduceInCoroutine($producerMessage, $connectionName);
    }

    private function doProduceInCoroutine(object $producerMessage, string $connectionName): bool
    {
        $channel = null;

        try {
            // Get pooled channel
            $channel = AMQPChannelPool::getChannel($connectionName);

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
            error_log("Error producing AMQP message: " . $e->getMessage());
            error_log($e->getTraceAsString());

            return false;
        }
    }
}