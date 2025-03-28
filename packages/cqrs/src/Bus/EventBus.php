<?php

namespace Ody\CQRS\Bus;

use Ody\CQRS\Bus\Middleware\EventBusMiddleware;
use Ody\CQRS\Interfaces\EventBus as EventBusInterface;

class EventBus implements EventBusInterface
{
    /**
     * @var EventBusInterface
     */
    protected EventBusInterface $bus;

    /**
     * @var array
     */
    protected array $middleware = [];

    /**
     * @param EventBusInterface $bus
     */
    public function __construct(EventBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * Add middleware to the event bus
     *
     * @param EventBusMiddleware $middleware
     * @return self
     */
    public function addMiddleware(EventBusMiddleware $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Publish an event through the middleware stack
     *
     * @param object $event
     * @return void
     */
    public function publish(object $event): void
    {
        $this->executeMiddlewareStack($event, 0);
    }

    /**
     * Execute middleware stack recursively
     *
     * @param object $event
     * @param int $index
     * @return void
     */
    protected function executeMiddlewareStack(object $event, int $index): void
    {
        if ($index >= count($this->middleware)) {
            // When we've gone through all middleware, publish the actual event
            $this->bus->publish($event);
            return;
        }

        // Execute the current middleware
        $middleware = $this->middleware[$index];

        $middleware->handle(
            $event,
            function ($event) use ($index) {
                // Move to the next middleware
                $this->executeMiddlewareStack($event, $index + 1);
            }
        );
    }
}