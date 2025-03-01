<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\StatusCommand;

use Ody\DB\Tests\Command\PgsqlCommandBehavior;

final class PgsqlStatusCommandTest extends StatusCommandTest
{
    use PgsqlCommandBehavior;
}
