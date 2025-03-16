<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\RollbackCommand;

use Ody\DB\Tests\Command\MysqlCommandBehavior;

final class MysqlRollbackCommandTest extends RollbackCommandTest
{
    use MysqlCommandBehavior;
}
