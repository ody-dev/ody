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

class TruncateTable extends AbstractMigration
{
    protected function up(): void
    {
        $this->table('table_6')->truncate();
    }

    protected function down(): void
    {
        $this->insert('table_6', [
            [
                'title' => 'Item 1',
            ],
            [
                'title' => 'Item 2',
            ],
            [
                'title' => 'Item 3',
            ],
        ]);
    }
}
