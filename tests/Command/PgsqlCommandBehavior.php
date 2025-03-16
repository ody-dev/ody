<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command;

use Ody\DB\Tests\Helpers\Adapter\PgsqlCleanupAdapter;
use Ody\DB\Tests\Helpers\Pdo\PgsqlPdo;

trait PgsqlCommandBehavior
{
    protected function getEnvironment(): string
    {
        return 'pgsql';
    }

    protected function getAdapter(): PgsqlCleanupAdapter
    {
        $pdo = new PgsqlPdo();
        return new PgsqlCleanupAdapter($pdo);
    }
}
