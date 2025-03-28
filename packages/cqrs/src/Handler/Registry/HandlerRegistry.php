<?php

namespace Ody\CQRS\Handler\Registry;

class HandlerRegistry
{
    /**
     * @var array
     */
    protected array $handlers = [];

    /**
     * Register a handler for a message class
     *
     * @param string $messageClass The fully qualified class name of the message
     * @param array $handlerInfo Information about the handler (class, method)
     * @return void
     */
    public function register(string $messageClass, array $handlerInfo): void
    {
        $this->handlers[$messageClass] = $handlerInfo;
    }

    /**
     * Check if a handler exists for a message class
     *
     * @param string $messageClass
     * @return bool
     */
    public function hasHandlerFor(string $messageClass): bool
    {
        return isset($this->handlers[$messageClass]);
    }

    /**
     * Get the handler for a message class
     *
     * @param string $messageClass
     * @return array|null
     */
    public function getHandlerFor(string $messageClass): ?array
    {
        return $this->handlers[$messageClass] ?? null;
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