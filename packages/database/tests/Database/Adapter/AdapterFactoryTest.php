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

use Ody\DB\Migrations\Config\EnvironmentConfig;
use Ody\DB\Migrations\Database\Adapter\AdapterFactory;
use Ody\DB\Migrations\Database\Adapter\MysqlAdapter;
use Ody\DB\Migrations\Database\Adapter\PgsqlAdapter;
use Ody\DB\Migrations\Database\QueryBuilder\MysqlQueryBuilder;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;
use PHPUnit\Framework\TestCase;

final class AdapterFactoryTest extends TestCase
{
    public function testMysql(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'mysql',
            'dsn' => 'sqlite::memory:',
        ]);
        $adapter = AdapterFactory::instance($config);
        $this->assertInstanceOf(MysqlAdapter::class, $adapter);
        $this->assertInstanceOf(MysqlQueryBuilder::class, $adapter->getQueryBuilder());
    }

    public function testPgsql(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'pgsql',
            'dsn' => 'sqlite::memory:',
        ]);
        $adapter = AdapterFactory::instance($config);
        $this->assertInstanceOf(PgsqlAdapter::class, $adapter);
    }

    public function testUnknown(): void
    {
        $config = new EnvironmentConfig([
            'adapter' => 'unknown',
            'dsn' => 'sqlite::memory:',
        ]);

        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Unknown adapter "unknown". Use one of value: "mysql", "pgsql".');
        AdapterFactory::instance($config);
    }
}
