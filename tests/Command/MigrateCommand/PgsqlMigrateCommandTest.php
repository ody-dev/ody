<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\MigrateCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlMigrateCommandTest extends MigrateCommandTest
{
    use PgsqlCommandBehavior;
}
