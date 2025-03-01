<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\DumpCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlDumpCommandTest extends DumpCommandTest
{
    use PgsqlCommandBehavior;
}
