<?php

declare(strict_types=1);

namespace Ody\ConnectionPool;

use PDO;

/**
 * @template TItem of PDO
 */
interface KeepaliveCheckerInterface
{
    /**
     * @param PDO|null $connection
     * @return bool
     */
    public function check(?PDO $connection): bool;

    public function getIntervalSec(): float;
}
