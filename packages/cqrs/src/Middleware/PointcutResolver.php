<?php

namespace Ody\CQRS\Middleware;

interface PointcutResolver
{
    /**
     * Check if the pointcut expression matches the target class/method
     *
     * @param string $pointcut The pointcut expression
     * @param string $targetClass The fully qualified class name
     * @param string|null $targetMethod The method name (optional)
     * @return bool
     */
    public function matches(string $pointcut, string $targetClass, ?string $targetMethod = null): bool;
}