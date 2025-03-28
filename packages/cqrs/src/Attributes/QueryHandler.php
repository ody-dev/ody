<?php

namespace Ody\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class QueryHandler
{
}