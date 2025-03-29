<?php

namespace Ody\CQRS\Middleware;

use Attribute;

/**
 * Base attribute for middleware interception
 */
#[Attribute(Attribute::TARGET_METHOD)]
abstract class InterceptorAttribute
{
    /**
     * @param int $priority Lower values run first
     * @param string $pointcut Expression to match target classes/methods
     */
    public function __construct(
        protected int    $priority = 10,
        protected string $pointcut = '*'
    )
    {
    }

    /**
     * Get the priority of the interceptor
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Get the pointcut expression
     */
    public function getPointcut(): string
    {
        return $this->pointcut;
    }
}

