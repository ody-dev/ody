<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command;

use Ody\DB\Tests\Helpers\Adapter\MysqlCleanupAdapter;
use Ody\DB\Tests\Helpers\Pdo\MysqlPdo;

trait MysqlCommandBehavior
{
    protected function getEnvironment(): string
    {
        return 'mysql';
    }

    protected function getAdapter(): MysqlCleanupAdapter
    {
        $pdo = new MysqlPdo();
        return new MysqlCleanupAdapter($pdo);
    }
}
