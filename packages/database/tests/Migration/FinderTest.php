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

use InvalidArgumentException;
use Ody\DB\Migrations\Migration\FilesFinder;
use PHPUnit\Framework\TestCase;

final class FilesFinderTest extends TestCase
{
    public function testDirectories(): void
    {
        $finder = new FilesFinder();
        $this->assertCount(0, $finder->getDirectories());
        $this->assertInstanceOf(FilesFinder::class, $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_1'));
        $this->assertCount(1, $finder->getDirectories());

        $this->assertInstanceOf(FilesFinder::class, $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_2'));
        $this->assertCount(2, $finder->getDirectories());

        $this->assertInstanceOf(FilesFinder::class, $finder->removeDirectory(__DIR__ . '/../fake/structure/migration_directory_1'));
        $this->assertCount(1, $finder->getDirectories());

        $this->assertInstanceOf(FilesFinder::class, $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_3'));
        $this->assertCount(2, $finder->getDirectories());

        // add same directory second time
        $this->assertInstanceOf(FilesFinder::class, $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_3'));
        $this->assertCount(2, $finder->getDirectories());

        $this->assertInstanceOf(FilesFinder::class, $finder->removeDirectories());
        $this->assertCount(0, $finder->getDirectories());
    }

    public function testAddNotExistingDirectory(): void
    {
        $finder = new FilesFinder();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory "not_existing_directory" not found');
        $finder->addDirectory('not_existing_directory');
    }

    public function testAddFileAsDirectory(): void
    {
        $finder = new FilesFinder();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"' . __DIR__ . '/../fake/structure/migration_directory_1/20150428140909_first_migration.php' . '" is not directory');
        $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_1/20150428140909_first_migration.php');
    }

    public function testRemoveNotAddedDirectory(): void
    {
        $finder = new FilesFinder();
        $this->expectException(InvalidArgumentException::class);
        $finder->removeDirectory('not_added_directory');
    }

    public function testGetMigrationFiles(): void
    {
        $finder = new FilesFinder();
        $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_1');
        $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_2');
        $finder->addDirectory(__DIR__ . '/../fake/structure/migration_directory_3');

        $files = $finder->getFiles();
        $this->assertTrue(is_array($files));
        $this->assertCount(4, $files);
    }
}
