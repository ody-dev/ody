<?php

namespace Ody\CQRS\Middleware;

class DefaultMethodInvocation implements MethodInvocation
{
    /**
     * @var callable Next middleware in the chain
     */
    private $next;

    /**
     * @param object $target The target object
     * @param string $method The method name
     * @param array $arguments The method arguments
     * @param callable $next The next middleware in the chain
     */
    public function __construct(
        private object $target,
        private string $method,
        private array  $arguments,
        callable       $next
    )
    {
        $this->next = $next;
    }

    /**
     * Get the target object
     */
    public function getTarget(): object
    {
        return $this->target;
    }

    /**
     * Get the method name
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the method arguments
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Proceed with the method invocation
     */
    public function proceed(): mixed
    {
        $next = $this->next;
        return $next($this->arguments);
    }
}