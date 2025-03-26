<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\Pool\Hook;

enum PoolItemHook: string
{
    case AFTER_RETURN = 'after_return';
    case BEFORE_BORROW = 'before_borrow';
}
