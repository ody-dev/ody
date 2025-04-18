<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Migration;

use Ody\DB\Migrations\Migration\MigrationNameCreator;
use PHPUnit\Framework\TestCase;

final class MigrationNameCreatorTest extends TestCase
{
    public function testCreateMigrationName(): void
    {
        $className = 'AddSomethingToTable';
        $migrationNameCreator = new MigrationNameCreator($className);
        $this->assertEquals(date('YmdHis') . '_add_something_to_table.php', $migrationNameCreator->getFileName());
        $this->assertEquals('AddSomethingToTable', $migrationNameCreator->getClassName());
        $this->assertEquals('', $migrationNameCreator->getNamespace());

        $className = '\AddSomethingToTable';
        $migrationNameCreator = new MigrationNameCreator($className);
        $this->assertEquals(date('YmdHis') . '_add_something_to_table.php', $migrationNameCreator->getFileName());
        $this->assertEquals('AddSomethingToTable', $migrationNameCreator->getClassName());
        $this->assertEquals('', $migrationNameCreator->getNamespace());

        $className = 'MyNamespace\AddSomethingToTable';
        $migrationNameCreator = new MigrationNameCreator($className);
        $this->assertEquals(date('YmdHis') . '_add_something_to_table.php', $migrationNameCreator->getFileName());
        $this->assertEquals('AddSomethingToTable', $migrationNameCreator->getClassName());
        $this->assertEquals('MyNamespace', $migrationNameCreator->getNamespace());

        $className = '\MyNamespace\SecondLevel\AddSomethingToTable';
        $migrationNameCreator = new MigrationNameCreator($className);
        $this->assertEquals(date('YmdHis') . '_add_something_to_table.php', $migrationNameCreator->getFileName());
        $this->assertEquals('AddSomethingToTable', $migrationNameCreator->getClassName());
        $this->assertEquals('MyNamespace\SecondLevel', $migrationNameCreator->getNamespace());
    }
}
