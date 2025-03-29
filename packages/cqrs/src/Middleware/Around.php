<?php

namespace Ody\CQRS\Middleware;

use Attribute;

/**
 * Wraps around the target method execution
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Around extends InterceptorAttribute
{
}