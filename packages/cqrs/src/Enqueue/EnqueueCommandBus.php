<?php

namespace Ody\CQRS\Enqueue;

use Enqueue\Client\ProducerInterface;
use Ody\Container\Container;
use Ody\CQRS\Exception\CommandHandlerException;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Interfaces\CommandBus as CommandBusInterface;

class EnqueueCommandBus implements CommandBusInterface
{
    /**
     * @var ProducerInterface
     */
    protected ProducerInterface $producer;

    /**
     * @var CommandHandlerRegistry
     */
    protected CommandHandlerRegistry $handlerRegistry;

    /**
     * @var CommandHandlerResolver
     */
    protected CommandHandlerResolver $handlerResolver;

    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * @param ProducerInterface $producer
     * @param CommandHandlerRegistry $handlerRegistry
     * @param CommandHandlerResolver $handlerResolver
     * @param Container $container
     * @param Configuration $configuration
     */
    public function __construct(
        ProducerInterface      $producer,
        CommandHandlerRegistry $handlerRegistry,
        CommandHandlerResolver $handlerResolver,
        Container              $container,
        Configuration          $configuration
    )
    {
        $this->producer = $producer;
        $this->handlerRegistry = $handlerRegistry;
        $this->handlerResolver = $handlerResolver;
        $this->container = $container;
        $this->configuration = $configuration;
    }

    /**
     * Dispatches a command to its handler
     *
     * @param object $command
     * @return void
     * @throws HandlerNotFoundException
     * @throws CommandHandlerException
     */
    public function dispatch(object $command): void
    {
        $commandClass = get_class($command);

        // Check if we have a registered handler
        if (!$this->handlerRegistry->hasHandlerFor($commandClass)) {
            throw new HandlerNotFoundException(
                sprintf('No handler found for command %s', $commandClass)
            );
        }

        // Get the handler information
        $handlerInfo = $this->handlerRegistry->getHandlerFor($commandClass);

        if ($this->configuration->isAsyncEnabled() && $this->configuration->shouldCommandRunAsync($commandClass)) {
            // Send to queue for async processing
            $this->producer->sendCommand(
                $this->configuration->getCommandTopic($commandClass),
                $command
            );

            return;
        }

        // Handle synchronously
        try {
            $handler = $this->handlerResolver->resolveHandler($handlerInfo);
            $handler($command);
        } catch (\Throwable $e) {
            throw new CommandHandlerException(
                sprintf('Error handling command %s: %s', $commandClass, $e->getMessage()),
                0,
                $e
            );
        }
    }
}