<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Comparator;

use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Database\Element\Structure;
use Ody\DB\Migrations\Database\Element\Table;

final class StructureComparator
{
    /**
     * @return MigrationTable[]
     */
    public function diff(Structure $sourceStructure, Structure $targetStructure): array
    {
        $diff = [];

        $sourceTables = $sourceStructure->getTables();
        $targetTables = $targetStructure->getTables();

        $tablesToDrop = array_diff(array_keys($sourceTables), array_keys($targetTables));
        foreach ($tablesToDrop as $tableToDropName) {
            $migrationTable = new MigrationTable($tableToDropName);
            $migrationTable->drop();
            $diff[] = $migrationTable;
        }

        $tablesToAdd = array_diff_key($targetTables, $sourceTables);
        /** @var Table $tableToAdd */
        foreach ($tablesToAdd as $tableToAdd) {
            $migrationTable = $tableToAdd->toMigrationTable();
            $migrationTable->create();
            $diff[] = $migrationTable;
        }

        $tableComparator = new TableComparator();
        $intersect = array_intersect(array_keys($sourceTables), array_keys($targetTables));
        foreach ($intersect as $tableName) {
            /** @var Table $sourceTable */
            $sourceTable = $sourceStructure->getTable($tableName);
            /** @var Table $targetTable */
            $targetTable = $targetStructure->getTable($tableName);

            $migrationTable = $tableComparator->diff($sourceTable, $targetTable);
            if ($migrationTable) {
                $diff[] = $migrationTable;
            }
        }

        return $diff;
    }
}
