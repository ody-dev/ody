<?php

namespace Ody\CQRS\Middleware;

use Attribute;

/**
 * Executes before the target method
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Before extends InterceptorAttribute
{
}