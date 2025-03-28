<?php

namespace Ody\CQRS\Handler\Resolver;

use Ody\Container\Container;
use Ody\CQRS\Interfaces\EventBus;

class CommandHandlerResolver extends HandlerResolver
{
    /**
     * @var EventBus|null
     */
    protected ?EventBus $eventBus;

    /**
     * @param Container $container
     * @param EventBus|null $eventBus
     */
    public function __construct(Container $container, ?EventBus $eventBus = null)
    {
        parent::__construct($container);
        $this->eventBus = $eventBus;
    }

    /**
     * Resolves a command handler from the handler info
     * Injects EventBus as a second parameter if the handler expects it
     *
     * @param array $handlerInfo
     * @return callable
     */
    public function resolveHandler(array $handlerInfo): callable
    {
        $handlerClass = $handlerInfo['class'];
        $handlerMethod = $handlerInfo['method'];

        $handler = $this->container->make($handlerClass);

        // Check if the method accepts EventBus as a second parameter
        $reflection = new \ReflectionMethod($handlerClass, $handlerMethod);
        $parameters = $reflection->getParameters();

        $expectsEventBus = count($parameters) > 1 &&
            $parameters[1]->getType() &&
            is_a(EventBus::class, $parameters[1]->getType()->getName(), true);

        if ($expectsEventBus && $this->eventBus) {
            return function ($command) use ($handler, $handlerMethod) {
                return $handler->$handlerMethod($command, $this->eventBus);
            };
        }

        return function ($command) use ($handler, $handlerMethod) {
            return $handler->$handlerMethod($command);
        };
    }
}