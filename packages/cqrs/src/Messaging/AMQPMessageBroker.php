<?php

namespace Ody\CQRS\Messaging;

use Ody\AMQP\AMQPClient;

class AMQPMessageBroker implements MessageBroker
{
    /**
     * @param AMQPClient $amqpClient
     */
    public function __construct(
        private readonly AMQPClient $amqpClient
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function send(string $channel, object $message): bool
    {
        // Get the producer class for this channel
        $producerClass = $this->getProducerClassForChannel($channel);

        // Send via AMQP client
        return $this->amqpClient->publish($producerClass, [$message]);
    }

    /**
     * Get the producer class name for a channel
     *
     * @param string $channel
     * @return string
     */
    private function getProducerClassForChannel(string $channel): string
    {
        // Convert channel name to producer class name
        // Example: 'order-processing' -> 'App\\Producers\\OrderProcessingProducer'
        $parts = explode('-', $channel);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return "App\\Producers\\{$className}Producer";
    }

    /**
     * {@inheritdoc}
     */
    public function receive(string $channel, callable $handler): bool
    {
        // This would set up a consumer in the AMQP system
        // The implementation depends on how your AMQP module handles consumers
        // This is typically handled by your AMQPBootstrap class
        return true;
    }
}