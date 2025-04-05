<?php

namespace App\Consumers;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\ConsumerMessage;
use Ody\AMQP\Message\Result;
use Ody\Container\Container;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Messaging\AsyncMessagingBootstrap;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

#[Consumer(exchange: 'commands', routingKey: '#', queue: 'commands', type: 'topic')]
class CommandsConsumer extends ConsumerMessage
{
    /**
     * @param CommandBusInterface $commandBus
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly LoggerInterface     $logger,
        private readonly Container           $container
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

            $this->logger->debug("Processing async command: {$commandClass}");

            // Look up the original handler for this command class
            $handlerRegistry = $this->commandBus->getHandlerRegistry();

            // If there's no handler, get it from the stored async handlers
            if (!$handlerRegistry->hasHandlerFor($commandClass)) {
                // Get the original handler info from AsyncMessagingBootstrap
                $asyncMessagingBootstrap = $this->container->get(AsyncMessagingBootstrap::class);
                $originalHandlerInfo = $asyncMessagingBootstrap->getOriginalHandlerInfo($commandClass);

                if ($originalHandlerInfo) {
                    // Temporarily register the original handler
                    $handlerRegistry->registerHandler(
                        $commandClass,
                        $originalHandlerInfo['class'],
                        $originalHandlerInfo['method']
                    );
                }
            }

            // Now dispatch the command
            $this->commandBus->dispatch($command);

            return Result::ACK;
        } catch (\Throwable $e) {
            $this->logger->error('Error processing async command: ' . $e);

            // For serious errors that shouldn't be retried
            if ($e instanceof \InvalidArgumentException) {
                return Result::DROP;
            }

            // For transient errors, requeue
            // TODO: drop for now, REQUEUE when it works
            return Result::DROP;
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