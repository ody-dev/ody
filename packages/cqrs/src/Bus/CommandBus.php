<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Exception\CommandHandlerException;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Interfaces\CommandBus as CommandBusInterface;

class CommandBus implements CommandBusInterface
{
    public function __construct(
        private CommandHandlerRegistry $handlerRegistry,
        private CommandHandlerResolver $handlerResolver
    )
    {
    }

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

        // Handle directly
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