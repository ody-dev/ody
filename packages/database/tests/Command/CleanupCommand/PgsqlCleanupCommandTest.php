<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\CleanupCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlCleanupCommandTest extends CleanupCommandTest
{
    use PgsqlCommandBehavior;
}
