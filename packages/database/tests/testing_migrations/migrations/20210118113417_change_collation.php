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

class ChangeCollation extends AbstractMigration
{
    protected function up(): void
    {
        $this->changeCollation('utf8mb4_general_ci');

        $this->insert('renamed_table_1', [
            'id' => 1000,
            'title' => 'Panda 🐼',
            'alias' => 'panda',
        ]);
    }

    protected function down(): void
    {
        $this->delete('renamed_table_1', ['id' => 1000]);
        $this->changeCollation('utf8_general_ci');
    }
}
