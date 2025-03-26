<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\ConnectionPool\Pool\Hook;

use Ody\ConnectionPool\ConnectionPool\Pool\PoolItemWrapperInterface;

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
