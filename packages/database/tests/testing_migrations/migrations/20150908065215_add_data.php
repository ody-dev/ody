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

class AddData extends AbstractMigration
{
    public function up(): void
    {
        $this->insert('table_1', [
            'title' => 'First item',
            'alias' => 'first-item',
            'is_active' => false,
            'self_fk' => null,
        ]);

        $this->insert('table_1', [
            [
                'title' => 'Second item',
                'alias' => 'second-item',
                'self_fk' => 10,
            ],
            [
                'title' => 'Third item',
                'alias' => 'third-item',
                'self_fk' => 11,
            ]
        ]);

        $this->insert('table_2', [
            'id' => 1,
            'title' => 'T2 First item',
            'sorting' => 1,
            't1_fk' => 10,
            'created_at' => new DateTime(),
        ]);

        $this->insert('table_2', [
            'id' => 2,
            'title' => 'T2 Second item',
            'sorting' => 2,
            't1_fk' => 12,
            'created_at' => new DateTime(),
        ]);

        $this->insert('table_3', [
            'identifier' => '6fedffa4-897e-41b1-ba00-185b7c1726d2',
            't1_fk' => 12,
        ]);

        $this->insert('table_3', [
            'identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465b',
            't1_fk' => 10,
            't2_fk' => 1,
        ]);

        $multiInsertData = [];
        for ($i = 1; $i <= 1000; $i++) {
            $multiInsertData[] = [
                'title' => 'Item ' . $i
            ];
        }
        $this->insert('table_4', $multiInsertData);

        $this->insert('table_5', [
            [
                'id' => 1,
                'title' => 'Item 1',
            ],
            [
                'id' => 2,
                'title' => 'Item 2',
            ],
            [
                'id' => 3,
                'title' => 'Item 3',
            ],
        ]);

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

        $this->update('table_1', [
            'title' => 'Renamed second item',
            'is_active' => true,
        ], ['id' => 11]);

        $this->insert('all_types', [
            'identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465c',
            'col_tinyinteger' => 50,
            'col_smallinteger' => 1,
            'col_mediuminteger' => 50,
            'col_integer' => 50,
            'col_bigint' => 1234567890,
            'col_string' => 'string',
            'col_char' => 'char',
            'col_text' => 'text',
            'col_json' => json_encode(['json' => 'my json']),
            'col_float' => 3.1415,
            'col_double' => 3.1415,
            'col_numeric' => 3.1415,
            'col_decimal' => 3.1415,
            'col_boolean' => true,
            'col_datetime' => new DateTime(),
            'col_date' => (new DateTime())->format('Y-m-d'),
            'col_enum' => 'xxx',
            'col_set' => ['yyy', 'zzz'],
            'col_time' => '10:20:30',
            'col_timestamp' => new DateTime(),
            'col_year' => 2020,
        ]);

        $this->insert('all_types', [
            'identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465d',
            'col_tinyinteger' => 50,
            'col_smallinteger' => 2,
            'col_mediuminteger' => 50,
            'col_integer' => 150,
            'col_bigint' => 9876543210,
            'col_string' => 'string',
            'col_char' => 'char',
            'col_text' => 'text',
            'col_json' => json_encode(['json' => 'my new json']),
            'col_float' => 3.1415,
            'col_double' => 3.1415,
            'col_decimal' => 3.1415,
            'col_numeric' => 3.1415,
            'col_datetime' => new DateTime(),
            'col_date' => (new DateTime())->format('Y-m-d'),
            'col_time' => '20:30:40',
            'col_timestamp' => null,
            'col_year' => 2021,
        ]);
    }

    protected function down(): void
    {
        $this->delete('all_types', ['identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465d']);
        $this->delete('all_types', ['identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465c']);
        $this->delete('table_6');
        $this->delete('table_5');
        $this->delete('table_4');
        $this->delete('table_3', ['identifier' => '914dbcc3-3b19-4b17-863b-2ce37a63465b']);
        $this->delete('table_3', ['identifier' => '6fedffa4-897e-41b1-ba00-185b7c1726d2']);
        $this->delete('table_2', ['id' => 2]);
        $this->delete('table_2', ['id' => 1]);
        $this->delete('table_1', ['id' => 10]);
        $this->delete('table_1', ['id' => 11]);
        $this->delete('table_1', ['id' => 12]);
        $this->table('table_1')->setAutoIncrement(10);
    }
}
