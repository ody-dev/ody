<?php

declare(strict_types=1);

namespace Ody\DB\ConnectionPool\Pool\Hook;

use Ody\DB\ConnectionPool\Pool\PoolItemWrapperInterface;

/**
 * @template TItem of object
 */
interface PoolItemHookManagerInterface
{
    /**
     * @param  PoolItemWrapperInterface<TItem>  $poolItemWrapper
     */
    public function run(PoolItemHook $poolHook, PoolItemWrapperInterface $poolItemWrapper): void;
}
