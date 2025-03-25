<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

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
