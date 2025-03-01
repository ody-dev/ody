<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\DiffCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlDiffCommandTest extends DiffCommandTest
{
    use PgsqlCommandBehavior;
}
