<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\CleanupCommand;

use Ody\DB\Tests\Command\MysqlCommandBehavior;

final class MysqlCleanupCommandTest extends CleanupCommandTest
{
    use MysqlCommandBehavior;
}
