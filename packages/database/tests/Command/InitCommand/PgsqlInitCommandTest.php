<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\InitCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlInitCommandTest extends InitCommandTest
{
    use PgsqlCommandBehavior;
}
