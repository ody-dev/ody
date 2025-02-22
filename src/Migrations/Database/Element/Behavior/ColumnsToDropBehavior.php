<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\Element\Behavior;

use Ody\DB\Migrations\Database\Element\MigrationTable;

trait ColumnsToDropBehavior
{
    /** @var string[] */
    private array $columnsToDrop = [];

    public function dropColumn(string $name): MigrationTable
    {
        $this->columnsToDrop[] = $name;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getColumnsToDrop(): array
    {
        return $this->columnsToDrop;
    }
}
