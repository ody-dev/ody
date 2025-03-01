<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Helpers\Adapter;

interface CleanupInterface
{
    public function cleanupDatabase(): void;
}
