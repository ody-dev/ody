<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Exception\CommandHandlerException;
use Ody\CQRS\Exception\HandlerNotFoundException;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use Throwable;

class CommandBus implements CommandBusInterface
{
    /**
     * @var array Custom command handlers registered at runtime
     */
    private array $customHandlers = [];

    /**
     * @var array List of middleware to apply
     */
    private array $middleware = [];

    /**
     * @param CommandHandlerRegistry $handlerRegistry
     * @param CommandHandlerResolver $handlerResolver
     * @param MiddlewareProcessor|null $middlewareProcessor
     */
    public function __construct(
        private CommandHandlerRegistry $handlerRegistry,
        private CommandHandlerResolver $handlerResolver,
        private ?MiddlewareProcessor   $middlewareProcessor = null
    )
    {
    }

    /**
     * Register a custom handler for a command
     *
     * @param string $commandClass Command class name
     * @param callable $handler Handler function
     * @return void
     */
    public function registerHandler(string $commandClass, callable $handler): void
    {
        $this->customHandlers[$commandClass] = $handler;
    }

    /**
     * Unregister a custom handler for a command
     *
     * @param string $commandClass Command class name
     * @return void
     */
    public function unregisterHandler(string $commandClass): void
    {
        unset($this->customHandlers[$commandClass]);
    }

    /**
     * Add middleware to the command bus
     *
     * @param object $middleware The middleware instance
     * @return self
     */
    public function addMiddleware(object $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatches a command to its handler
     *
     * @param object $command
     * @return void
     * @throws HandlerNotFoundException
     * @throws CommandHandlerException|Throwable
     */
    public function dispatch(object $command): void
    {
        $commandClass = get_class($command);

        // Check for custom handler first
        if (isset($this->customHandlers[$commandClass])) {
            $handler = $this->customHandlers[$commandClass];
            $handler($command);
            return;
        }

        // Check if we have a registered handler
        if (!$this->handlerRegistry->hasHandlerFor($commandClass)) {
            throw new HandlerNotFoundException(
                sprintf('No handler found for command %s', $commandClass)
            );
        }

        // Get the handler information
        $handlerInfo = $this->handlerRegistry->getHandlerFor($commandClass);

        // If we have a middleware processor, use it
        if ($this->middlewareProcessor !== null) {
            try {
                $this->middlewareProcessor->process(
                    $this,
                    'executeHandler',
                    [$command, $handlerInfo],
                    function ($args) {
                        $this->executeHandler(...$args);
                    }
                );
            } catch (Throwable $e) {
                throw new CommandHandlerException(
                    sprintf('Error handling command %s: %s', $commandClass, $e->getMessage()),
                    0,
                    $e
                );
            }
        } else {
            // Otherwise, just execute the handler directly
            $this->executeHandler($command, $handlerInfo);
        }
    }

    /**
     * Execute the handler for a command
     *
     * @param object $command The command to handle
     * @param array $handlerInfo The handler information
     * @return void
     * @throws Throwable
     */
    public function executeHandler(object $command, array $handlerInfo): void
    {
        try {
            $handler = $this->handlerResolver->resolveHandler($handlerInfo);
            $handler($command);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * @return CommandHandlerRegistry
     */
    public function getHandlerRegistry(): CommandHandlerRegistry
    {
        return $this->handlerRegistry;
    }
}