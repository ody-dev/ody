<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\RollbackCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlRollbackCommandTest extends RollbackCommandTest
{
    use PgsqlCommandBehavior;
}
