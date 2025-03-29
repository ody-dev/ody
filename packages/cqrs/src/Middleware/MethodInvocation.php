<?php

namespace Ody\CQRS\Middleware;

interface MethodInvocation
{
    /**
     * Get the target object
     */
    public function getTarget(): object;

    /**
     * Get the method name
     */
    public function getMethod(): string;

    /**
     * Get the method arguments
     */
    public function getArguments(): array;

    /**
     * Proceed with the method invocation
     */
    public function proceed(): mixed;
}