<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\TestingMigrations;

use Ody\DB\Migrations\Migration\AbstractMigration;

class SetAutoIncrement extends AbstractMigration
{
    protected function up(): void
    {
        $this->table('renamed_table_1')->setAutoIncrement(100);

        $this->insert('renamed_table_1', [
            [
                'title' => 'Item #100',
                'alias' => 'item-100',
            ],
            [
                'title' => 'Item #101',
                'alias' => 'item-101',
            ],
        ]);
    }

    protected function down(): void
    {
        $this->delete('renamed_table_1', ['id' => [100, 101]]);
        $this->table('renamed_table_1')->setAutoIncrement(100);
    }
}
