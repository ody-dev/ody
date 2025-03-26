<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\Pool;

enum PoolItemState: string
{
    case IDLE = 'idle';
    case IN_USE = 'in_use';
    case REMOVED = 'removed';
    case RESERVED = 'reserved';
}
