<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Attributes\Producer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionAttribute;
use ReflectionClass;

class MessageProcessor
{
    /**
     * @var array<string, object>
     */
    private array $consumers = [];

    /**
     * @var array<string, object>
     */
    private array $producers = [];

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

    public function startConsumers(array $config, string $poolName = 'default'): void
    {
        $connection = ConnectionManager::getConnection($poolName);

        foreach ($this->consumers as $consumer) {
            $consumerInstance = $consumer['instance'];
            $consumerAttribute = $consumer['attribute'];

            if (!$consumerAttribute->enable) {
                continue;
            }

            // Start the appropriate number of consumer processes
            for ($i = 0; $i < $consumerAttribute->nums; $i++) {
                // Use your existing Task system to process the consumer
                // This is where your Swoole process/task system integration happens
                $this->startConsumerProcess($consumerInstance, $consumerAttribute, $connection);
            }
        }
    }

    public function produce(object $producerMessage, string $poolName = 'default'): bool
    {
        $connection = ConnectionManager::getConnection($poolName);
        $channel = $connection->channel();

        $reflection = new ReflectionClass($producerMessage);
        $attributes = $reflection->getAttributes(Producer::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            throw new \RuntimeException("Message class must have Producer attribute");
        }

        $producerAttribute = $attributes[0]->newInstance();

        // Declare exchange
        $channel->exchange_declare(
            $producerAttribute->exchange,
            $producerAttribute->type,
            false,
            true,
            false
        );

        // Create and publish message
        $message = new AMQPMessage(
            json_encode($producerMessage->getPayload()),
            $producerMessage->getProperties()
        );

        $channel->basic_publish(
            $message,
            $producerAttribute->exchange,
            $producerAttribute->routingKey
        );

        $channel->close();

        return true;
    }
}