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
