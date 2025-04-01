<?php

namespace Ody\CQRS\Messaging;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\ConsumerMessage;
use Ody\AMQP\Message\Result;
use Ody\CQRS\Interfaces\CommandBusInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'async_commands', routingKey: '#', queue: 'async_commands', type: 'topic')]
class AsyncCommandConsumer extends ConsumerMessage
{
    /**
     * @param CommandBusInterface $commandBus
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly LoggerInterface     $logger
    )
    {
    }

    /**
     * Process a received message
     *
     * @param array $data
     * @param AMQPMessage $message
     * @return Result
     */
    public function consumeMessage(array $data, AMQPMessage $message): Result
    {
        try {
            // Extract command class and data
            if (!isset($data['command_class']) || !isset($data['command_data'])) {
                $this->logger->error('Invalid async command message format');
                return Result::DROP;
            }

            $commandClass = $data['command_class'];
            $commandData = $data['command_data'];

            // Reconstruct the command object
            $command = $this->reconstructCommand($commandClass, $commandData);

            // Execute the command directly through the command bus
            $this->logger->info("Processing async command: {$commandClass}");
            $this->commandBus->dispatch($command);

            return Result::ACK;
        } catch (\Throwable $e) {
            $this->logger->error('Error processing async command: ' . $e->getMessage());

            // For serious errors that shouldn't be retried
            if ($e instanceof \InvalidArgumentException) {
                return Result::DROP;
            }

            // For transient errors, requeue
            return Result::REQUEUE;
        }
    }

    /**
     * Reconstruct a command from class name and data
     *
     * @param string $commandClass
     * @param array $commandData
     * @return object
     * @throws \ReflectionException
     */
    private function reconstructCommand(string $commandClass, array $commandData): object
    {
        // Use reflection to create the command
        $reflection = new \ReflectionClass($commandClass);

        // If there's a constructor, use it with the data
        if ($constructor = $reflection->getConstructor()) {
            $params = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                if (isset($commandData[$paramName])) {
                    $params[] = $commandData[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException("Missing required parameter: {$paramName}");
                }
            }
            return $reflection->newInstanceArgs($params);
        }

        // If no constructor, create instance and set properties
        $command = $reflection->newInstance();
        foreach ($commandData as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setValue($command, $value);
            }
        }

        return $command;
    }
}