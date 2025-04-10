<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\TestingMigrations;

use DateTime;
use Ody\DB\Migrations\Migration\AbstractMigration;

class AddDataAfterChanges extends AbstractMigration
{
    public function up(): void
    {
        $this->insert('all_types', [
            'identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465e',
            'col_tinyinteger' => 50,
            'col_smallinteger' => 3,
            'col_mediuminteger' => 50,
            'col_integer' => 50,
            'col_bigint' => 1234567890,
            'col_string' => 'string',
            'col_char' => 'char',
            'col_text' => 'text',
            'col_json' => json_encode(['json' => 'my json']),
            'col_float' => 3.1415,
            'col_double' => 3.1415,
            'col_decimal' => 3.1415,
            'col_numeric' => 3.1415,
            'col_boolean' => true,
            'col_datetime' => new DateTime(),
            'col_date' => (new DateTime())->format('Y-m-d'),
            'col_enum' => 'qqq',
            'col_set' => ['yyy', 'qqq'],
            'col_year' => 2000,
        ]);
    }

    protected function down(): void
    {
        $this->delete('all_types', ['identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465e']);
    }
}
