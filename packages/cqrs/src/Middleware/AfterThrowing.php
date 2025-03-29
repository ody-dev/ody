<?php

namespace Ody\CQRS\Middleware;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AfterThrowing extends InterceptorAttribute
{
}