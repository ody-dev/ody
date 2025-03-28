<?php

namespace Ody\CQRS\Handler\Registry;

class EventHandlerRegistry
{
    /**
     * @var array
     */
    protected array $handlers = [];

    /**
     * Register an event handler
     *
     * @param string $eventClass
     * @param string $handlerClass
     * @param string $handlerMethod
     * @return void
     */
    public function registerHandler(
        string $eventClass,
        string $handlerClass,
        string $handlerMethod
    ): void
    {
//        error_log('EventHandlerRegistry::registerHandler: ' . $eventClass);
        if (!isset($this->handlers[$eventClass])) {
            $this->handlers[$eventClass] = [];
        }

        $this->handlers[$eventClass][] = [
            'class' => $handlerClass,
            'method' => $handlerMethod,
        ];
    }

    /**
     * Check if any handlers exist for an event class
     *
     * @param string $eventClass
     * @return bool
     */
    public function hasHandlersFor(string $eventClass): bool
    {
        return isset($this->handlers[$eventClass]) && !empty($this->handlers[$eventClass]);
    }

    /**
     * Get all handlers for an event class
     *
     * @param string $eventClass
     * @return array
     */
    public function getHandlersFor(string $eventClass): array
    {
        return $this->handlers[$eventClass] ?? [];
    }

    /**
     * Get all registered handlers
     *
     * @return array
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }
}