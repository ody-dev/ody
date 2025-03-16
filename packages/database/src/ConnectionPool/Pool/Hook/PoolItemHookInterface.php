<?php

declare(strict_types=1);

namespace Ody\DB\ConnectionPool\Pool\Hook;

use Ody\DB\ConnectionPool\Pool\PoolItemWrapperInterface;

/**
 * @template TItem of object
 */
interface PoolItemHookInterface
{
    /**
     * @param  PoolItemWrapperInterface<TItem>  $poolItemWrapper
     */
    public function invoke(PoolItemWrapperInterface $poolItemWrapper): void;

    public function getHook(): PoolItemHook;
}
