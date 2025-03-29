<?php

namespace Ody\CQRS\Bus;

use Ody\Container\Container;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Interfaces\EventBus as EventBusInterface;

class EventBus implements EventBusInterface
{
    /**
     * @param EventHandlerRegistry $handlerRegistry
     * @param Container $container
     */
    public function __construct(
        private EventHandlerRegistry $handlerRegistry,
        private Container            $container
    )
    {
    }

    /**
     * Publishes an event to all subscribers
     *
     * @param object $event
     * @return void
     */
    public function publish(object $event): void
    {
        $eventClass = get_class($event);

        // Get all handlers for this event
        $handlerInfos = $this->handlerRegistry->getHandlersFor($eventClass);

        // Execute each handler
        foreach ($handlerInfos as $handlerInfo) {
            $handlerClass = $handlerInfo['class'];
            $handlerMethod = $handlerInfo['method'];

            $handler = $this->container->make($handlerClass);
            $handler->$handlerMethod($event);
        }
    }
}