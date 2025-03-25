<?php

declare(strict_types=1);

namespace Ody\DB\ConnectionPool\Pool;

/**
 * @template TItem of object
 */
interface PoolItemFactoryInterface
{
    /**
     * @return TItem
     * @throws Exceptions\PoolItemCreationException
     */
    public function create(): mixed;
}
