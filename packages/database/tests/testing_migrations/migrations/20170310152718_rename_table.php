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

class RenameTables extends AbstractMigration
{
    public function up(): void
    {
        $this->table('table_1')->rename('renamed_table_1');
    }

    protected function down(): void
    {
        $this->table('renamed_table_1')->rename('table_1');
    }
}
