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

class RenameColumn extends AbstractMigration
{
    public function up(): void
    {
        $this->table('table_4')->renameColumn('identifier', 'id')->save();
    }

    protected function down(): void
    {
        $this->table('table_4')->renameColumn('id', 'identifier')->save();
    }
}
