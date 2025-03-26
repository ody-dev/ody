<?php

declare(strict_types=1);

namespace Ody\ConnectionPool;

/**
 * @template TConnection of object
 */
interface KeepaliveCheckerInterface
{
    /**
     * @param  TConnection|null  $connection
     */
    public function check(mixed $connection): bool;

    public function getIntervalSec(): float;
}
