<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\TestingMigrations;

use Exception;
use Ody\DB\Migrations\Database\Element\Column;
use Ody\DB\Migrations\Database\Element\Table;
use Ody\DB\Migrations\Migration\AbstractMigration;

class Check extends AbstractMigration
{
    public function up(): void
    {
        $rowZero = $this->fetch('migrations_log', ['*'], ['id' => 0]);
        if ($rowZero !== null) {
            throw new Exception('Row zero should not exist');
        }

        $logs = $this->fetchAll('migrations_log');
        if (count($logs) !== 14) {
            throw new Exception('Wrong count');
        }

        $res = $this->select('SELECT COUNT(*) AS log_count FROM migrations_log');
        if (count($logs) !== intval($res[0]['log_count'])) {
            throw new Exception('Counts don\'t match');
        }

        $tableColumns = [
            'renamed_table_1' => [
                'id', 'title', 'alias', 'is_active', 'bodytext', 'self_fk',
            ],
            'table_2' => [
                'id', 'title', 'new_sorting', 't1_fk', 'created_at',
            ],
            'table_3' => [
                'identifier', 't1_fk', 't2_fk', 'id',
            ],
            'table_4' => [
                'title', 'id',
            ],
            'table_5' => [
                'id', 'title',
            ],
            'table_6' => [
                'id', 'title',
            ],
            'new_table_2' => [
                'id', 'title', 'new_sorting', 't1_fk', 'created_at',
            ],
            'new_table_3' => [
                'identifier', 't1_fk', 't2_fk', 'id',
            ],
        ];

        foreach ($tableColumns as $table => $columns) {
            $firstItem = $this->fetch($table);
            if ($table === 'table_6') {
                if ($firstItem) {
                    throw new Exception('There should be no data in table "' . $table . '"');
                }
            } elseif (count($firstItem) !== count($columns)) {
                throw new Exception('Wrong number of columns in first item of table ' . $table);
            }
            $items = $this->fetchAll($table);
            if ($table === 'table_6') {
                if (count($items) > 0) {
                    throw new Exception('There should be no data in table "' . $table . '"');
                }
            } elseif (count($items) === 0) {
                throw new Exception('No data in table "' . $table . '"');
            }
            foreach ($items as $item) {
                if (count($item) !== count($columns)) {
                    throw new Exception('Wrong number of columns in item');
                }
                foreach ($columns as $column) {
                    if (!array_key_exists($column, $item)) {
                        throw new Exception('Column "' . $column . '" is not defined in item');
                    }
                }
            }
        }

        $table4Count = $this->fetch('table_4', ['count(*) as cnt']);
        if (intval($table4Count['cnt']) !== 1000) {
            throw new Exception('Items count in table_4 is not 1000, but ' . $table4Count['cnt']);
        }

        $item1 = $this->fetch('renamed_table_1', ['*'], ['id' => 10]);
        if ((bool)$item1['is_active'] !== false) {
            throw new Exception('is_active for item with id 10 should be false');
        }

        $item2 = $this->fetch('renamed_table_1', ['*'], ['id' => 11]);
        if ((bool)$item2['is_active'] !== true) {
            throw new Exception('is_active for item with id 11 should be true');
        }

        $item3 = $this->fetch('renamed_table_1', ['*'], ['id' => 12]);
        if ((bool)$item3['is_active'] !== true) {
            throw new Exception('is_active for item with id 12 should be false');
        }

        $item100 = $this->fetch('renamed_table_1', ['*'], ['id' => 100]);
        if (!$item100) {
            throw new Exception('There is no item with id 100');
        }
        if ((bool)$item100['is_active'] !== true) {
            throw new Exception('is_active for item with id 100 should be false');
        }

        $item1000 = $this->fetch('renamed_table_1', ['title'], ['id' => 1000]);
        if (!$item1000) {
            throw new Exception('There is no item with id 1000');
        }
        if ($item1000['title'] !== 'Panda 🐼') {
            throw new Exception('title for item with id 1000 should be "Panda 🐼"');
        }

        if ($this->tableExists('non_existing_table')) {
            throw new Exception('non_existing_table exists!');
        }

        if ($this->tableColumnExists('non_existing_table', 'some_column')) {
            throw new Exception('non_existing_table.some_column exists!');
        }

        if ($this->tableIndexExists('non_existing_table', 'some_column')) {
            throw new Exception('non_existing_table.some_column exists!');
        }

        if ($this->tableColumnExists('table_2', 'non_existing_column')) {
            throw new Exception('table_2.non_existing_column exists!');
        }

        if ($this->tableIndexExists('table_2', 'non_existing_column')) {
            throw new Exception('table_2.non_existing_column exists!');
        }

        if ($this->getTable('non_existing_table') !== null) {
            throw new Exception('non_existing_table is not null');
        }

        if (!($this->getTable('table_2') instanceof Table)) {
            throw new Exception('table_2 is not a Table');
        }

        if (!($this->getTable('table_2')->getColumn('title') instanceof Column)) {
            throw new Exception('table_2.title is not a Column');
        }
    }

    public function down(): void
    {
    }
}
