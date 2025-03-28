<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Bus\Middleware\CommandBusMiddleware;
use Ody\CQRS\Interfaces\CommandBus as CommandBusInterface;

class CommandBus implements CommandBusInterface
{
    /**
     * @var CommandBusInterface
     */
    protected CommandBusInterface $bus;

    /**
     * @var array
     */
    protected array $middleware = [];

    /**
     * @param CommandBusInterface $bus
     */
    public function __construct(CommandBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * Add middleware to the command bus
     *
     * @param CommandBusMiddleware $middleware
     * @return self
     */
    public function addMiddleware(CommandBusMiddleware $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch a command through the middleware stack
     *
     * @param object $command
     * @return void
     */
    public function dispatch(object $command): void
    {
        $this->executeMiddlewareStack($command, 0);
    }

    /**
     * Execute middleware stack recursively
     *
     * @param object $command
     * @param int $index
     * @return void
     */
    protected function executeMiddlewareStack(object $command, int $index): void
    {
        if ($index >= count($this->middleware)) {
            // When we've gone through all middleware, execute the actual command
            $this->bus->dispatch($command);
            return;
        }

        // Execute the current middleware
        $middleware = $this->middleware[$index];

        $middleware->handle(
            $command,
            function ($command) use ($index) {
                // Move to the next middleware
                $this->executeMiddlewareStack($command, $index + 1);
            }
        );
    }
}