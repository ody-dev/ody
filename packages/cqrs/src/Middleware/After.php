<?php

namespace Ody\CQRS\Middleware;

use Attribute;

/**
 * Executes after the target method returns
 */
#[Attribute(Attribute::TARGET_METHOD)]
class After extends InterceptorAttribute
{
}