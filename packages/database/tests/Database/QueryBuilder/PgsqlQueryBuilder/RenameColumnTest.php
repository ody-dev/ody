<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Database\QueryBuilder\PgsqlQueryBuilder;

use Ody\DB\Migrations\Database\Adapter\PgsqlAdapter;
use Ody\DB\Migrations\Database\QueryBuilder\PgsqlQueryBuilder;
use Ody\DB\Migrations\Database\Element\MigrationTable;
use Ody\DB\Tests\Helpers\Pdo\PgsqlPdo;
use PHPUnit\Framework\TestCase;

final class RenameColumnTest extends TestCase
{
    private PgsqlAdapter $adapter;

    protected function setUp(): void
    {
        $pdo = new PgsqlPdo(getenv('ODY_PGSQL_DATABASE'));
        $this->adapter = new PgsqlAdapter($pdo);
    }

    public function testSimpleRenameColumn(): void
    {
        $queryBuilder = new PgsqlQueryBuilder($this->adapter);

        $table = new MigrationTable('test_table');
        $table->renameColumn('asdf', 'alias');

        $expectedQueries = [
            'ALTER TABLE "test_table" RENAME COLUMN "asdf" TO "alias";',
        ];
        $this->assertEquals($expectedQueries, $queryBuilder->alterTable($table));
    }
}
