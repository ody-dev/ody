<?php

declare(strict_types=1);

namespace Ody\DB\ConnectionPool\Pool;

use LogicException;

readonly class PoolConfig
{
    /**
     * @param  positive-int  $size
     */
    public function __construct(
        public int $size,
        public float $borrowingTimeoutSec,
        public float $returningTimeoutSec,
        public bool $autoReturn = false,
        public bool $bindToCoroutine = false,
    ) {
        if ($autoReturn && !$bindToCoroutine) {
            throw new LogicException('Auto return can only work with coroutine binding');
        }
    }
}
