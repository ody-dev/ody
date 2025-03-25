<?php

declare(strict_types=1);

namespace Ody\DB\ConnectionPool\Pool;

/**
 * @template TItem of object
 */
interface PoolItemWrapperFactoryInterface
{
    /**
     * @return PoolItemWrapperInterface<TItem>
     */
    public function create(): PoolItemWrapperInterface;
}
