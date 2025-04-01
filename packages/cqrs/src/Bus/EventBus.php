<?php

namespace Ody\CQRS\Bus;

use Ody\Container\Container;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Interfaces\EventBusInterface;
use Ody\CQRS\Middleware\MiddlewareProcessor;
use Psr\Log\LoggerInterface;

class EventBus implements EventBusInterface
{
    /**
     * @var array List of middleware to apply
     */
    private array $middleware = [];

    /**
     * @param EventHandlerRegistry $handlerRegistry
     * @param Container $container
     * @param MiddlewareProcessor|null $middlewareProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private EventHandlerRegistry $handlerRegistry,
        private Container            $container,
        private LoggerInterface $logger,
        private ?MiddlewareProcessor $middlewareProcessor = null,
    )
    {
    }

    /**
     * Add middleware to the event bus
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

        // If we have a middleware processor, use it for the dispatch process itself
        if ($this->middlewareProcessor !== null) {
            try {
                $this->middlewareProcessor->process(
                    $this,
                    'executeHandlers',
                    [$event, $handlerInfos],
                    function ($args) {
                        $this->executeHandlers(...$args);
                    }
                );
            } catch (\Throwable $e) {
                // Log the error but continue, as events should not disrupt the flow
                $this->logger->error(sprintf('Error publishing event %s: %s', $eventClass, $e->getMessage()));
            }
        } else {
            // Otherwise, just execute the handlers directly
            $this->executeHandlers($event, $handlerInfos);
        }
    }

    /**
     * Execute all handlers for an event
     *
     * @param object $event The event to handle
     * @param array $handlerInfos The handler information array
     * @return void
     */
    public function executeHandlers(object $event, array $handlerInfos): void
    {
        foreach ($handlerInfos as $handlerInfo) {
            try {
                $handlerClass = $handlerInfo['class'];
                $handlerMethod = $handlerInfo['method'];

                $handler = $this->container->make($handlerClass);

                // If we have a middleware processor, apply it to each handler execution
                if ($this->middlewareProcessor !== null) {
                    $this->middlewareProcessor->process(
                        $handler,
                        $handlerMethod,
                        [$event],
                        function ($args) use ($handler, $handlerMethod) {
                            return $handler->$handlerMethod(...$args);
                        }
                    );
                } else {
                    // Otherwise, just call the handler directly
                    $handler->$handlerMethod($event);
                }
            } catch (\Throwable $e) {
                // Log the error but continue with other handlers
                $this->logger->error(sprintf(
                    'Error handling event %s in %s::%s: %s',
                    get_class($event),
                    $handlerInfo['class'],
                    $handlerInfo['method'],
                    $e->getMessage()
                ));
            }
        }
    }
}