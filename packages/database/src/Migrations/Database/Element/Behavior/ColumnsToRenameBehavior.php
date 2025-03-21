<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Database\Element\Behavior;

use Ody\DB\Migrations\Database\Element\MigrationTable;

trait ColumnsToRenameBehavior
{
    /** @var array<string, string> */
    private array $columnsToRename = [];

    public function renameColumn(string $oldName, string $newName): MigrationTable
    {
        $this->columnsToRename[$oldName] = $newName;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getColumnsToRename(): array
    {
        return $this->columnsToRename;
    }
}
