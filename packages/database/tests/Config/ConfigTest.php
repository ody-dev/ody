<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Config;

use Ody\DB\Migrations\Config\Config;
use Ody\DB\Migrations\Config\EnvironmentConfig;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;

final class ConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new Config([
            'migration_dirs' => [
                'first_dir',
                'second_dir'
            ],
            'environments' => [
                'first' => [],
                'second' => [],
                'third' => [],
            ],
        ]);
        $this->assertEquals('migrations_log', $config->getLogTableName());
        $this->assertCount(2, $config->getMigrationDirs());
        $this->assertEquals('first', $config->getDefaultEnvironment());
        $this->assertInstanceOf(EnvironmentConfig::class, $config->getEnvironmentConfig('first'));
        $this->assertInstanceOf(EnvironmentConfig::class, $config->getEnvironmentConfig('second'));
        $this->assertNull($config->getEnvironmentConfig('not_existing_config'));
        $this->assertTrue(is_array($config->getConfiguration()));
        $this->assertArrayHasKey('migration_dirs', $config->getConfiguration());
        $this->assertCount(2, $config->getConfiguration()['migration_dirs']);
        $this->assertCount(3, $config->getConfiguration()['environments']);
    }

    public function testOverridenDefaults(): void
    {
        $config = new Config([
            'default_environment' => 'second',
            'log_table_name' => 'custom_log_table_name',
            'migration_dirs' => [
                'first_dir',
                'second_dir'
            ],
            'environments' => [
                'first' => [],
                'second' => [],
            ],
        ]);
        $this->assertEquals('custom_log_table_name', $config->getLogTableName());
        $this->assertCount(2, $config->getMigrationDirs());
        $this->assertEquals('second', $config->getDefaultEnvironment());
        $this->assertInstanceOf(EnvironmentConfig::class, $config->getEnvironmentConfig('first'));
        $this->assertInstanceOf(EnvironmentConfig::class, $config->getEnvironmentConfig('second'));
        $this->assertNull($config->getEnvironmentConfig('not_existing_config'));
    }

    public function testEmptyMigrationDirs(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Empty migration dirs');
        new Config([
            'environments' => [
                'first' => [],
            ],
        ]);
    }

    public function testSelectTheOnlyMigrationDir(): void
    {
        $config = new Config([
            'default_environment' => 'second',
            'log_table_name' => 'custom_log_table_name',
            'migration_dirs' => [
                'first_dir',
            ],
            'environments' => [
                'first' => [],
                'second' => [],
            ],
        ]);
        $this->assertEquals('first_dir', $config->getMigrationDir());
        $this->assertEquals('first_dir', $config->getMigrationDir('0'));
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Directory "xxx" doesn\'t exist. Use: 0');
        $config->getMigrationDir('xxx');
    }

    public function testSelectMigrationDir(): void
    {
        $config = new Config([
            'default_environment' => 'second',
            'log_table_name' => 'custom_log_table_name',
            'migration_dirs' => [
                'first_dir',
                'second_dir'
            ],
            'environments' => [
                'first' => [],
                'second' => [],
            ],
        ]);

        $this->assertEquals('first_dir', $config->getMigrationDir('0'));
        $this->assertEquals('second_dir', $config->getMigrationDir('1'));
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('There are more then 1 migration dirs. Use one of them: 0, 1');
        $config->getMigrationDir();
    }

    public function testSelectTheOnlyNamedMigrationDir(): void
    {
        $config = new Config([
            'default_environment' => 'second',
            'log_table_name' => 'custom_log_table_name',
            'migration_dirs' => [
                'first' => 'first_dir',
            ],
            'environments' => [
                'first' => [],
                'second' => [],
            ],
        ]);

        $this->assertEquals('first_dir', $config->getMigrationDir());
        $this->assertEquals('first_dir', $config->getMigrationDir('first'));
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Directory "xxx" doesn\'t exist. Use: first');
        $config->getMigrationDir('xxx');
    }

    public function testSelectNamedMigrationDir(): void
    {
        $config = new Config([
            'default_environment' => 'second',
            'log_table_name' => 'custom_log_table_name',
            'migration_dirs' => [
                'first' => 'first_dir',
                'second' => 'second_dir'
            ],
            'environments' => [
                'first' => [],
                'second' => [],
            ],
        ]);

        $this->assertEquals('first_dir', $config->getMigrationDir('first'));
        $this->assertEquals('second_dir', $config->getMigrationDir('second'));
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Directory "xxx" doesn\'t exist. Use: first, second');
        $config->getMigrationDir('xxx');
    }

    public function testEmptyEnvironments(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Empty environments');
        new Config([
            'migration_dirs' => [
                'first_dir'
            ],
        ]);
    }

    public function testDependencies(): void
    {
        $config = new Config([
            'migration_dirs' => [
                'first_dir',
                'second_dir'
            ],
            'environments' => [
                'first' => [],
                'second' => [],
                'third' => [],
            ],
            'dependencies' => [
                UuidFactoryInterface::class => new UuidFactory(),
            ],
        ]);

        $this->assertInstanceOf(UuidFactory::class, $config->getDependency(UuidFactoryInterface::class));

        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Dependency for type "Ramsey\Uuid\UuidFactory" not found. Register it via $configuration[\'dependencies\'][\'Ramsey\Uuid\UuidFactory\']');
        $config->getDependency(UuidFactory::class);
    }
}
