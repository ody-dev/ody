<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\Pool\Hook;

use Ody\ConnectionPool\Pool\PoolItemWrapperInterface;

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
