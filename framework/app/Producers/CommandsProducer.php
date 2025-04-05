<?php

namespace App\Producers;

use Ody\AMQP\Attributes\Producer;
use Ody\AMQP\Message\ProducerMessage;

#[Producer(exchange: 'commands', routingKey: '', type: 'topic')]
class CommandsProducer extends ProducerMessage
{
    /**
     * @param object $command The command to send asynchronously
     */
    public function __construct(object $command)
    {
        $this->payload = [
            'command_class' => get_class($command),
            'command_data' => $this->serializeCommand($command),
            'timestamp' => microtime(true)
        ];

        // Set the routing key to the command class to allow specific routing
        $routingKey = $this->getRoutingKeyFromCommand($command);

        // Set producer properties
        $this->properties = [
            'content_type' => 'application/json',
            'delivery_mode' => 2, // persistent
            'app_id' => 'ody_cqrs',
            'message_id' => uniqid('cmd_', true),
            'timestamp' => time(),
            'routing_key' => $routingKey
        ];
    }

    /**
     * Serialize a command object to an array
     *
     * @param object $command
     * @return array
     */
    private function serializeCommand(object $command): array
    {
        $data = [];
        $reflection = new \ReflectionClass($command);

        // Get data from properties without calling setAccessible
        foreach ($reflection->getProperties() as $property) {
            $data[$property->getName()] = $property->getValue($command);
        }

        return $data;
    }

    /**
     * Generate a routing key from the command class
     *
     * @param object $command
     * @return string
     */
    private function getRoutingKeyFromCommand(object $command): string
    {
        $className = get_class($command);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        // Convert CamelCase to kebab-case for the routing key
        $kebabCase = preg_replace('/([a-z])([A-Z])/', '$1-$2', $shortName);
        return strtolower($kebabCase);
    }
}