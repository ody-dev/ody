<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Database\Adapter;

use Ody\DB\Migrations\Database\Adapter\PgsqlAdapter;
use Ody\DB\Migrations\Database\Element\Column;
use Ody\DB\Migrations\Database\Element\ForeignKey;
use Ody\DB\Migrations\Database\Element\Index;
use Ody\DB\Migrations\Database\Element\IndexColumn;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Database\Element\Structure;
use Ody\DB\Migrations\Database\Element\Table;
use Ody\DB\Migrations\Database\QueryBuilder\PgsqlQueryBuilder;
use Ody\DB\Migrations\Database\QueryBuilder\QueryBuilderInterface;
use Ody\DB\Migrations\Exception\DatabaseQueryExecuteException;
use Ody\DB\Tests\Helpers\Adapter\PgsqlCleanupAdapter;
use Ody\DB\Tests\Helpers\Pdo\PgsqlPdo;
use PHPUnit\Framework\TestCase;

final class PgsqlAdapterTest extends TestCase
{
    private PgsqlAdapter $adapter;

    protected function setUp(): void
    {
        $pdo = new PgsqlPdo();
        $adapter = new PgsqlCleanupAdapter($pdo);
        $adapter->cleanupDatabase();

        $pdo = new PgsqlPdo(getenv('ODY_PGSQL_DATABASE'));
        $this->adapter = new PgsqlAdapter($pdo);
    }

    public function testGetQueryBuilder(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->adapter->getQueryBuilder());
        $this->assertInstanceOf(PgsqlQueryBuilder::class, $this->adapter->getQueryBuilder());
    }

    public function testForeignKeyQueries(): void
    {
        $this->assertEquals('SET session_replication_role = DEFAULT;', $this->adapter->buildCheckForeignKeysQuery());
        $this->assertEquals('SET session_replication_role = replica;', $this->adapter->buildDoNotCheckForeignKeysQuery());
    }

    public function testGetEmptyStructureAndUpdate(): void
    {
        $structure = $this->adapter->getStructure();
        $this->assertInstanceOf(Structure::class, $structure);
        $this->assertEmpty($structure->getTables());
        $this->assertNull($structure->getTable('structure_test'));

        $migrationTable = new MigrationTable('structure_test');
        $migrationTable->addColumn('title', 'string');
        $migrationTable->create();
        $structure->update($migrationTable);

        $this->assertCount(1, $structure->getTables());
        $this->assertInstanceOf(Table::class, $structure->getTable('structure_test'));

        $queryBuilder = $this->adapter->getQueryBuilder();
        $queries = $queryBuilder->createTable($migrationTable);
        foreach ($queries as $query) {
            $this->adapter->query($query);
        }

        $updatedStructure = $this->adapter->getStructure();
        $this->assertInstanceOf(Structure::class, $updatedStructure);
        $this->assertCount(1, $updatedStructure->getTables());
        $this->assertInstanceOf(Table::class, $structure->getTable('structure_test'));
    }

    public function testUniqueIndexWithLengthSpecified(): void
    {
        $queryBuilder = $this->adapter->getQueryBuilder();

        $migrationTable = new MigrationTable('unique_index_length_10');
        $migrationTable->addColumn('title', 'string');
        $migrationTable->addColumn('alias', 'string');
        $migrationTable->addColumn('blabla', 'text');
        $migrationTable->addIndex(new IndexColumn('title', ['length' => 10]), Index::TYPE_UNIQUE);
        $migrationTable->addIndex([new IndexColumn('title', ['length' => 15, 'order' => 'DESC']), new IndexColumn('alias', ['length' => 10])]);
        $migrationTable->addIndex([new IndexColumn('title', ['length' => 10]), new IndexColumn('alias', ['length' => 15, 'order' => 'DESC']), new IndexColumn('blabla', ['length' => 20])]);
        $migrationTable->create();

        $queries = $queryBuilder->createTable($migrationTable);
        foreach ($queries as $query) {
            $this->adapter->query($query);
        }

        $structure = $this->adapter->getStructure();
        $this->assertInstanceOf(Structure::class, $structure);
        $this->assertCount(1, $structure->getTables());
        // check table
        $table = $structure->getTable('unique_index_length_10');
        $this->assertInstanceOf(Table::class, $table);

        $defaultSettings = [
            'charset' => null,
            'collation' => null,
            'default' => null,
            'null' => false,
            'length' => null,
            'decimals' => null,
            'autoincrement' => false,
            'signed' => true,
            'values' => null,
            'comment' => null,
        ];

        // check columns
        $this->checkColumn($table, 'id', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'autoincrement' => true,
        ]));
        $this->checkColumn($table, 'title', Column::TYPE_STRING, array_merge($defaultSettings, [
            'length' => 255,
        ]));

        $this->checkIndex($table, 'idx_unique_index_length_10_title_l10', [new IndexColumn('title',['length' => 10])], Index::TYPE_UNIQUE, Index::METHOD_DEFAULT);
        $this->checkIndex($table, 'idx_unique_index_length_10_title_l15_odesc_alias_l10', [new IndexColumn('title',['length' => 15, 'order' => 'DESC']), new IndexColumn('alias',['length' => 10])], Index::TYPE_NORMAL, Index::METHOD_DEFAULT);
        $this->checkIndex($table, 'idx_unique_index_length_10_title_l10_alias_l15_odesc_blabla_l20', [new IndexColumn('title', ['length' => 10]), new IndexColumn('alias', ['length' => 15, 'order' => 'DESC']), new IndexColumn('blabla', ['length' => 20])], Index::TYPE_NORMAL, Index::METHOD_DEFAULT);

        $this->adapter->insert('unique_index_length_10', [
            'title' => 'This is my item number 1',
            'alias' => 'this-is-my-item-number-1',
            'blabla' => 'Foo',
        ]);

        $this->expectException(DatabaseQueryExecuteException::class);
        $this->adapter->insert('unique_index_length_10', [
            'title' => 'This is my item number 2',
            'alias' => 'this-is-my-item-number-2',
            'blabla' => 'Bar',
        ]);
    }

    public function testIndexWithOrderSpecified(): void
    {
        $queryBuilder = $this->adapter->getQueryBuilder();

        $migrationTable = new MigrationTable('ordered_index');
        $migrationTable->setCollation('utf8_general_ci');
        $migrationTable->addColumn('created_at', 'datetime');
        $migrationTable->addColumn('updated_at', 'datetime');
        $migrationTable->addColumn('title', 'string');
        $migrationTable->addIndex([
            new IndexColumn('created_at', ['order' => 'DESC']),
            new IndexColumn('updated_at', ['order' => 'ASC']),
            new IndexColumn('title', ['order' => 'DESC'])
        ]);
        $migrationTable->create();

        $queries = $queryBuilder->createTable($migrationTable);
        foreach ($queries as $query) {
            $this->adapter->query($query);
        }

        $structure = $this->adapter->getStructure();
        $this->assertInstanceOf(Structure::class, $structure);
        $this->assertCount(1, $structure->getTables());
        // check table
        $table = $structure->getTable('ordered_index');
        $this->assertInstanceOf(Table::class, $table);

        $defaultSettings = [
            'charset' => null,
            'collation' => null,
            'default' => null,
            'null' => false,
            'length' => null,
            'decimals' => null,
            'autoincrement' => false,
            'signed' => true,
            'values' => null,
            'comment' => null,
        ];

        // check columns
        $this->checkColumn($table, 'id', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'autoincrement' => true,
        ]));
        $this->checkColumn($table, 'created_at', Column::TYPE_DATETIME, array_merge($defaultSettings, [

        ]));
        $this->checkColumn($table, 'updated_at', Column::TYPE_DATETIME, array_merge($defaultSettings, [

        ]));
        $this->checkColumn($table, 'title', Column::TYPE_STRING, array_merge($defaultSettings, [
            'length' => 255,
        ]));

        $this->checkIndex($table, 'idx_ordered_index_created_at_odesc_updated_at_title_odesc', [
            new IndexColumn('created_at', ['order' => 'DESC']),
            new IndexColumn('updated_at'),
            new IndexColumn('title', ['order' => 'DESC'])
        ], Index::TYPE_NORMAL, Index::METHOD_DEFAULT);
    }

    public function testFullStructure(): void
    {
        $this->prepareStructure();

        $structure = $this->adapter->getStructure();
        $this->assertInstanceOf(Structure::class, $structure);
        $this->assertCount(2, $structure->getTables());
        // check all tables
        $table1 = $structure->getTable('table_1');
        $this->assertInstanceOf(Table::class, $table1);
        $this->assertEquals('', $table1->getComment());
        $table2 = $structure->getTable('table_2');
        $this->assertInstanceOf(Table::class, $table2);
        $this->assertEquals('Comment for table_2', $table2->getComment());

        $defaultSettings = [
            'charset' => null,
            'collation' => null,
            'default' => null,
            'null' => false,
            'length' => null,
            'decimals' => null,
            'autoincrement' => false,
            'signed' => true,
            'values' => null,
            'comment' => null,
        ];

        // check all columns and their settings for table_1
        $this->assertEquals(['id'], $table1->getPrimary());
        $this->checkColumn($table1, 'id', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'autoincrement' => true,
        ]));
        $this->checkColumn($table1, 'col_uuid', Column::TYPE_UUID, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_bit', Column::TYPE_BIT, array_merge($defaultSettings, [
            'length' => 5,
        ]));
        $this->checkColumn($table1, 'col_tinyint', Column::TYPE_SMALL_INTEGER, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_smallint', Column::TYPE_SMALL_INTEGER, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_mediumint', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_int', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'default' => 50,
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_bigint', Column::TYPE_BIG_INTEGER, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_string', Column::TYPE_STRING, array_merge($defaultSettings, [
            'length' => 255,
        ]));
        $this->checkColumn($table1, 'col_char', Column::TYPE_CHAR, array_merge($defaultSettings, [
            'length' => 50,
        ]));
        $this->checkColumn($table1, 'col_binary', Column::TYPE_BLOB, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_varbinary', Column::TYPE_BLOB, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_tinytext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_mediumtext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_text', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_longtext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_tinyblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table1, 'col_mediumblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table1, 'col_blob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table1, 'col_longblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table1, 'col_json', Column::TYPE_JSON, array_merge($defaultSettings, []));
        $this->checkColumn($table1, 'col_numeric', Column::TYPE_NUMERIC, array_merge($defaultSettings, [
            'length' => 10,
            'decimals' => 3,
        ]));
        $this->checkColumn($table1, 'col_decimal', Column::TYPE_NUMERIC, array_merge($defaultSettings, [
            'length' => 11,
            'decimals' => 2,
        ]));
        $this->checkColumn($table1, 'col_float', Column::TYPE_FLOAT, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_double', Column::TYPE_DOUBLE, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_boolean', Column::TYPE_BOOLEAN, array_merge($defaultSettings, [
            'default' => true,
        ]));
        $this->checkColumn($table1, 'col_datetime', Column::TYPE_DATETIME, $defaultSettings);
        $this->checkColumn($table1, 'col_date', Column::TYPE_DATE, $defaultSettings);
        $this->checkColumn($table1, 'col_enum', Column::TYPE_ENUM, array_merge($defaultSettings, [
            'values' => ['t1_enum_xxx', 't1_enum_yyy', 't1_enum_zzz'],
        ]));
        $this->checkColumn($table1, 'col_set', Column::TYPE_SET, array_merge($defaultSettings, [
            'values' => ['t1_set_xxx', 't1_set_yyy', 't1_set_zzz'],
        ]));
        $this->checkColumn($table1, 'col_point', Column::TYPE_POINT, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_line', Column::TYPE_LINE, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table1, 'col_polygon', Column::TYPE_POLYGON, array_merge($defaultSettings, [
            'null' => true,
        ]));

        // check all indexes for table_1
        $this->assertCount(3, $table1->getIndexes());

        // TODO try to load hash and btree methods
        $this->checkIndex($table1, 'idx_table_1_col_int', [new IndexColumn('col_int')], Index::TYPE_NORMAL, Index::METHOD_DEFAULT);
        $this->checkIndex($table1, 'idx_table_1_col_string', [new IndexColumn('col_string')], Index::TYPE_UNIQUE, Index::METHOD_DEFAULT);
        // $this->checkIndex($table1, 'idx_table_1_col_text', [new IndexColumn('col_text')], Index::TYPE_FULLTEXT, Index::METHOD_DEFAULT);
        $this->checkIndex($table1, 'idx_table_1_col_mediumint_col_bigint', [new IndexColumn('col_mediumint'), new IndexColumn('col_bigint')], Index::TYPE_NORMAL, Index::METHOD_DEFAULT);

        // check all foreign keys for table_1
        $this->assertCount(0, $table1->getForeignKeys());

        // check all columns and their settings for table_2
        $this->assertEquals(['id'], $table2->getPrimary());
        $this->checkColumn($table2, 'id', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'autoincrement' => true,
        ]));
        $this->checkColumn($table2, 'col_uuid', Column::TYPE_UUID, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_bit', Column::TYPE_BIT, array_merge($defaultSettings, [
            'null' => true,
            'length' => 5,
        ]));
        $this->checkColumn($table2, 'col_tinyint', Column::TYPE_SMALL_INTEGER, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_smallint', Column::TYPE_SMALL_INTEGER, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_mediumint', Column::TYPE_INTEGER, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_int', Column::TYPE_INTEGER, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table2, 'col_bigint', Column::TYPE_BIG_INTEGER, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table2, 'col_string', Column::TYPE_STRING, array_merge($defaultSettings, [
            'null' => true,
            'length' => 50,
        ]));
        $this->checkColumn($table2, 'col_char', Column::TYPE_CHAR, array_merge($defaultSettings, [
            'length' => 255,
        ]));
        $this->checkColumn($table2, 'col_binary', Column::TYPE_BLOB, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table2, 'col_varbinary', Column::TYPE_BLOB, array_merge($defaultSettings, [
            'null' => true,
        ]));
        $this->checkColumn($table2, 'col_tinytext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_mediumtext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_text', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_longtext', Column::TYPE_TEXT, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_tinyblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table2, 'col_mediumblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table2, 'col_blob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table2, 'col_longblob', Column::TYPE_BLOB, $defaultSettings);
        $this->checkColumn($table2, 'col_json', Column::TYPE_JSON, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_numeric', Column::TYPE_NUMERIC, array_merge($defaultSettings, [
            'null' => true,
            'length' => 10,
            'decimals' => 0,
        ]));
        $this->checkColumn($table2, 'col_decimal', Column::TYPE_NUMERIC, array_merge($defaultSettings, [
            'null' => true,
            'length' => 11,
            'decimals' => 0,
        ]));
        $this->checkColumn($table2, 'col_float', Column::TYPE_FLOAT, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_double', Column::TYPE_DOUBLE, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_boolean', Column::TYPE_BOOLEAN, array_merge($defaultSettings, []));
        $this->checkColumn($table2, 'col_datetime', Column::TYPE_DATETIME, array_merge($defaultSettings, [
            'null' => true
        ]));
        $this->checkColumn($table2, 'col_date', Column::TYPE_DATE, array_merge($defaultSettings, [
            'null' => true
        ]));

        $this->checkColumn($table2, 'col_enum', Column::TYPE_ENUM, array_merge($defaultSettings, [
            'null' => true,
            'values' => ['t2_enum_xxx', 't2_enum_yyy', 't2_enum_zzz'],
        ]));
        $this->checkColumn($table2, 'col_set', Column::TYPE_SET, array_merge($defaultSettings, [
            'null' => true,
            'values' => ['t2_set_xxx', 't2_set_yyy', 't2_set_zzz'],
        ]));
        $this->checkColumn($table2, 'col_point', Column::TYPE_POINT, array_merge($defaultSettings, [
            'comment' => 'Comment for "point"',
        ]));
        $this->checkColumn($table2, 'col_line', Column::TYPE_LINE, array_merge($defaultSettings, [
            'comment' => "Line's comment",
        ]));
        $this->checkColumn($table2, 'col_polygon', Column::TYPE_POLYGON, array_merge($defaultSettings, [
            'comment' => 'Polygon column comment',
        ]));

        // check all indexes for table_2
        $this->assertCount(1, $table2->getIndexes());
        $this->assertNull($table2->getIndex('idx_table_2_col_string'));

        // TODO try to load hash and btree methods
        $this->checkIndex($table2, 'named_unique_index', [new IndexColumn('col_string')], Index::TYPE_UNIQUE, Index::METHOD_DEFAULT);
        // $this->checkIndex($table2, 'named_unique_index', [new IndexColumn('col_string')], Index::TYPE_UNIQUE, Index::METHOD_BTREE);
        // index based on foreign key is not created
        $this->assertNull($table2->getIndex('table_2_col_int'));

        // check all foreign keys for table_2
        $this->assertCount(1, $table2->getForeignKeys());
        $foreignKey = $table2->getForeignKey('col_int');
        $this->assertInstanceOf(ForeignKey::class, $foreignKey);
        $this->assertEquals('col_int', $foreignKey->getName());
        $this->assertEquals(['col_int'], $foreignKey->getColumns());
        $this->assertEquals('table_1', $foreignKey->getReferencedTable());
        $this->assertEquals(['id'], $foreignKey->getReferencedColumns());
        $this->assertEquals(ForeignKey::SET_NULL, $foreignKey->getOnDelete());
        $this->assertEquals(ForeignKey::CASCADE, $foreignKey->getOnUpdate());
    }

    private function prepareStructure(): void
    {
        $queryBuilder = $this->adapter->getQueryBuilder();

        $migrationTable1 = new MigrationTable('table_1', true);
        $migrationTable1->setCollation('utf8_general_ci');
        $migrationTable1->addColumn('col_uuid', 'uuid', ['null' => true]);
        $migrationTable1->addColumn('col_bit', 'bit', ['length' => 5]);
        $migrationTable1->addColumn('col_tinyint', 'tinyinteger', ['null' => true]);
        $migrationTable1->addColumn('col_smallint', 'smallinteger', ['null' => true, 'signed' => false]);
        $migrationTable1->addColumn('col_mediumint', 'mediuminteger', ['null' => true]);
        $migrationTable1->addColumn('col_int', 'integer', ['null' => true, 'default' => 50, 'signed' => false]);
        $migrationTable1->addColumn('col_bigint', 'biginteger');
        $migrationTable1->addColumn('col_string', 'string');
        $migrationTable1->addColumn('col_char', 'char', ['length' => 50, 'charset' => 'utf16']);
        $migrationTable1->addColumn('col_binary', 'binary');
        $migrationTable1->addColumn('col_varbinary', 'varbinary');
        $migrationTable1->addColumn('col_tinytext', 'tinytext');
        $migrationTable1->addColumn('col_mediumtext', 'mediumtext');
        $migrationTable1->addColumn('col_text', 'text');
        $migrationTable1->addColumn('col_longtext', 'longtext');
        $migrationTable1->addColumn('col_tinyblob', 'tinyblob');
        $migrationTable1->addColumn('col_mediumblob', 'mediumblob');
        $migrationTable1->addColumn('col_blob', 'blob');
        $migrationTable1->addColumn('col_longblob', 'longblob');
        $migrationTable1->addColumn('col_json', 'json');
        $migrationTable1->addColumn('col_numeric', 'numeric', ['length' => 10, 'decimals' => 3]);
        $migrationTable1->addColumn('col_decimal', 'decimal', ['length' => 11, 'decimals' => 2]);
        $migrationTable1->addColumn('col_float', 'float', ['null' => true, 'length' => 12, 'decimals' => 4]);
        $migrationTable1->addColumn('col_double', 'double', ['null' => true, 'length' => 13, 'decimals' => 1, 'signed' => false]);
        $migrationTable1->addColumn('col_boolean', 'boolean', ['default' => true]);
        $migrationTable1->addColumn('col_datetime', 'datetime');
        $migrationTable1->addColumn('col_date', 'date');
        $migrationTable1->addColumn('col_enum', 'enum', ['values' => ['t1_enum_xxx', 't1_enum_yyy', 't1_enum_zzz']]);
        $migrationTable1->addColumn('col_set', 'set', ['values' => ['t1_set_xxx', 't1_set_yyy', 't1_set_zzz']]);
        $migrationTable1->addColumn('col_point', 'point', ['null' => true]);
        $migrationTable1->addColumn('col_line', 'line', ['null' => true]);
        $migrationTable1->addColumn('col_polygon', 'polygon', ['null' => true]);
        $migrationTable1->addIndex('col_int', Index::TYPE_NORMAL, Index::METHOD_HASH);
        $migrationTable1->addIndex('col_string', Index::TYPE_UNIQUE);
//        $migrationTable1->addIndex('col_text', Index::TYPE_FULLTEXT);
        $migrationTable1->addIndex(['col_mediumint', 'col_bigint']);
        $migrationTable1->create();
        $queries1 = $queryBuilder->createTable($migrationTable1);
        foreach ($queries1 as $query) {
            $this->adapter->query($query);
        }

        $migrationTable2 = new MigrationTable('table_2');
        $migrationTable2->setCollation('utf8_slovak_ci');
        $migrationTable2->setComment('Comment for table_2');
        $migrationTable2->addColumn('col_uuid', 'uuid');
        $migrationTable2->addColumn('col_bit', 'bit', ['null' => true, 'length' => 5]);
        $migrationTable2->addColumn('col_tinyint', 'tinyinteger', ['signed' => false]);
        $migrationTable2->addColumn('col_smallint', 'smallinteger');
        $migrationTable2->addColumn('col_mediumint', 'mediuminteger', ['signed' => false]);
        $migrationTable2->addColumn('col_int', 'integer', ['null' => true]);
        $migrationTable2->addColumn('col_bigint', 'biginteger', ['null' => true, 'signed' => false]);
        $migrationTable2->addColumn('col_string', 'string', ['null' => true, 'length' => 50, 'collation' => 'utf16_slovak_ci']);
        $migrationTable2->addColumn('col_char', 'char');
        $migrationTable2->addColumn('col_binary', 'binary', ['null' => true, 'length' => 50]);
        $migrationTable2->addColumn('col_varbinary', 'varbinary', ['null' => true, 'length' => 50]);
        $migrationTable2->addColumn('col_tinytext', 'tinytext');
        $migrationTable2->addColumn('col_mediumtext', 'mediumtext');
        $migrationTable2->addColumn('col_text', 'text');
        $migrationTable2->addColumn('col_longtext', 'longtext');
        $migrationTable2->addColumn('col_tinyblob', 'tinyblob');
        $migrationTable2->addColumn('col_mediumblob', 'mediumblob');
        $migrationTable2->addColumn('col_blob', 'blob');
        $migrationTable2->addColumn('col_longblob', 'longblob');
        $migrationTable2->addColumn('col_json', 'json');
        $migrationTable2->addColumn('col_numeric', 'numeric', ['null' => true]);
        $migrationTable2->addColumn('col_decimal', 'decimal', ['null' => true, 'length' => 11, 'decimals' => 0]);
        $migrationTable2->addColumn('col_float', 'float');
        $migrationTable2->addColumn('col_double', 'double');
        $migrationTable2->addColumn('col_boolean', 'boolean');
        $migrationTable2->addColumn('col_datetime', 'datetime', ['null' => true]);
        $migrationTable2->addColumn('col_date', 'date', ['null' => true]);
        $migrationTable2->addColumn('col_enum', 'enum', ['null' => true, 'values' => ['t2_enum_xxx', 't2_enum_yyy', 't2_enum_zzz']]);
        $migrationTable2->addColumn('col_set', 'set', ['null' => true, 'values' => ['t2_set_xxx', 't2_set_yyy', 't2_set_zzz']]);
        $migrationTable2->addColumn('col_point', 'point', ['comment' => 'Comment for "point"']);
        $migrationTable2->addColumn('col_line', 'line', ['comment' => 'Line\'s comment']);
        $migrationTable2->addColumn('col_polygon', 'polygon', ['comment' => 'Polygon column comment']);
        $migrationTable2->addIndex(['col_string'], Index::TYPE_UNIQUE, Index::METHOD_BTREE, 'named_unique_index');
        $migrationTable2->addForeignKey('col_int', 'table_1', 'id', ForeignKey::SET_NULL, ForeignKey::CASCADE);
        $migrationTable2->create();
        $queries2 = $queryBuilder->createTable($migrationTable2);
        foreach ($queries2 as $query) {
            $this->adapter->query($query);
        }
    }

    private function checkColumn(Table $table, $name, $type, array $expectedSettings)
    {
        $column = $table->getColumn($name);
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals($name, $column->getName());
        $this->assertEquals($type, $column->getType());
        $this->assertEquals($expectedSettings['charset'], $column->getSettings()->getCharset());
        $this->assertEquals($expectedSettings['collation'], $column->getSettings()->getCollation());
        $this->assertEquals($expectedSettings['default'], $column->getSettings()->getDefault());
        $this->assertEquals($expectedSettings['null'], $column->getSettings()->allowNull());
        $this->assertEquals($expectedSettings['length'], $column->getSettings()->getLength());
        $this->assertEquals($expectedSettings['decimals'], $column->getSettings()->getDecimals());
        $this->assertEquals($expectedSettings['autoincrement'], $column->getSettings()->isAutoincrement());
        $this->assertEquals($expectedSettings['signed'], $column->getSettings()->isSigned());
        $this->assertEquals($expectedSettings['values'], $column->getSettings()->getValues());
        $this->assertEquals($expectedSettings['comment'], $column->getSettings()->getComment());
    }

    private function checkIndex(Table $table, string $name, array $columns, string $type, string $method): void
    {
        $index = $table->getIndex($name);
        $this->assertInstanceOf(Index::class, $index);
        $this->assertEquals($name, $index->getName());
        $this->assertEquals($columns, $index->getColumns());
        $this->assertEquals($type, $index->getType());
        $this->assertEquals($method, $index->getMethod());
    }
}
