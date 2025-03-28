<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Database\Element;

use Ody\DB\Migrations\Database\Element\Column;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Migrations\Database\Element\Structure;
use Ody\DB\Migrations\Database\Element\Table;
use PHPUnit\Framework\TestCase;

final class StructureTest extends TestCase
{
    public function testEmpty(): void
    {
        $structure = new Structure();
        $this->assertEquals([], $structure->getTables());
        $this->assertNull($structure->getTable('some_table'));
    }

    public function testAddSimpleTable(): void
    {
        $structure = new Structure();

        $this->assertCount(0, $structure->getTables());
        $this->assertEquals([], $structure->getTables());
        $this->assertNull($structure->getTable('test'));

        $migrationTable = new MigrationTable('test');
        $migrationTable->addColumn('title', 'string');
        $migrationTable->create();
        $this->assertInstanceOf(Structure::class, $structure->update($migrationTable));
        $this->assertCount(1, $structure->getTables());
        $table = $structure->getTable('test');
        $this->assertInstanceOf(Table::class, $table);
        $this->assertEquals('test', $table->getName());
        $this->assertCount(2, $table->getColumns());
        $idColumn = $table->getColumn('id');
        $this->assertInstanceOf(Column::class, $idColumn);
        $titleColumn = $table->getColumn('title');
        $this->assertInstanceOf(Column::class, $titleColumn);
    }
}
