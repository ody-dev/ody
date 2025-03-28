<?php

namespace Ody\CQRS\Enqueue;

use Enqueue\Client\ProducerInterface;
use Illuminate\Contracts\Container\Container;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Interfaces\EventBus as EventBusInterface;

class EnqueueEventBus implements EventBusInterface
{
    /**
     * @var ProducerInterface
     */
    protected ProducerInterface $producer;

    /**
     * @var EventHandlerRegistry
     */
    protected EventHandlerRegistry $handlerRegistry;

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
     * @param EventHandlerRegistry $handlerRegistry
     * @param Container $container
     * @param Configuration $configuration
     */
    public function __construct(
        ProducerInterface    $producer,
        EventHandlerRegistry $handlerRegistry,
        Container            $container,
        Configuration        $configuration
    )
    {
        $this->producer = $producer;
        $this->handlerRegistry = $handlerRegistry;
        $this->container = $container;
        $this->configuration = $configuration;
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

        // Events are typically processed asynchronously
        if ($this->configuration->isAsyncEnabled()) {
            // Send to queue for async processing
            $this->producer->sendEvent(
                $this->configuration->getEventTopic($eventClass),
                $event
            );

            return;
        }

        // If async is disabled, handle the event synchronously
        // Note: Events can have multiple handlers
        $handlerInfos = $this->handlerRegistry->getHandlersFor($eventClass);

        foreach ($handlerInfos as $handlerInfo) {
            $handlerClass = $handlerInfo['class'];
            $handlerMethod = $handlerInfo['method'];

            $handler = $this->container->make($handlerClass);
            $handler->$handlerMethod($event);
        }
    }
}