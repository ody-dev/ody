<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Command\CreateCommand;

use Ody\DB\Migrations\Command\CreateCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\PhoenixException;
use Ody\DB\Migrations\Migration\ClassNameCreator;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;

abstract class CreateCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new CreateCommand();
        $this->assertEquals('migrations:create', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new CreateCommand('my_create');
        $this->assertEquals('my_create', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new CreateCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new CreateCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testNonExistingTemplateFileException(): void
    {
        $command = new CreateCommand();
        $command->setConfig($this->configuration);
        $this->input->setArgument('migration', '\MyNamespace\MyFirstMigration');
        $this->input->setOption('template', 'non-existing-file.phoenix');
        $this->input->setOption('indent', '4spaces');

        $this->expectException(PhoenixException::class);
        $this->expectExceptionMessage('Template "non-existing-file.phoenix" not found');
        $command->run($this->input, $this->output);
    }

    public function testMoreThanOneMigrationDirsAvailableWithCommandChoice(): void
    {
        $createMigrationDir = __DIR__ . '/../../testing_migrations/new';
        $this->assertFalse(is_dir($createMigrationDir));
        mkdir($createMigrationDir);
        $this->assertTrue(is_dir($createMigrationDir));

        $configuration = $this->configuration;
        $configuration['migration_dirs']['create'] = $createMigrationDir;

        $command = new CreateCommand();
        $command->setConfig($configuration);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['create']);
        $commandTester->execute(['migration' => 'test\test']);

        $createFiles = Finder::create()->files()->in($createMigrationDir);
        $this->assertCount(1, $createFiles);
        foreach ($createFiles as $createFile) {
            $filePath = (string)$createFile;

            $migrationContent = file_get_contents($filePath);
            $this->assertStringStartsWith('<?php

declare(strict_types=1);', $migrationContent);
            $this->assertStringNotContainsString("\t", $migrationContent);
            $this->assertStringContainsString("    ", $migrationContent);

            $classNameCreator = new ClassNameCreator($filePath);
            $this->assertEquals('\Test\Test', $classNameCreator->getClassName());
            unlink($filePath);
        }
        rmdir($createMigrationDir);
    }

    public function testCreateMigrationInNewDirectory(): void
    {
        $createMigrationDir = __DIR__ . '/../../testing_migrations/new';
        $this->assertFalse(is_dir($createMigrationDir));
        mkdir($createMigrationDir);
        $this->assertTrue(is_dir($createMigrationDir));

        $configuration = $this->configuration;
        $configuration['migration_dirs']['create'] = $createMigrationDir;

        $initCommand = new InitCommand();
        $input = $this->createInput();
        $initCommand->setConfig($configuration);
        $initCommand->run($input, new Output());

        $createFiles = Finder::create()->files()->in($createMigrationDir);
        $this->assertCount(0, $createFiles);

        $command = new CreateCommand();
        $command->setConfig($configuration);
        $this->input->setArgument('migration', '\MyNamespace\MyFirstMigration');
        $this->input->setArgument('dir', 'create');
        $this->input->setOption('indent', '4spaces');
        $command->run($this->input, $this->output);

        $createFiles = Finder::create()->files()->in($createMigrationDir);
        $this->assertCount(1, $createFiles);
        foreach ($createFiles as $createFile) {
            $filePath = (string)$createFile;

            $migrationContent = file_get_contents($filePath);
            $this->assertStringStartsWith('<?php

declare(strict_types=1);', $migrationContent);
            $this->assertStringNotContainsString("\t", $migrationContent);
            $this->assertStringContainsString("    ", $migrationContent);

            $classNameCreator = new ClassNameCreator($filePath);
            $this->assertEquals('\MyNamespace\MyFirstMigration', $classNameCreator->getClassName());
            unlink($filePath);
        }

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);

        $this->assertCount(5, $messages[0]);
        $this->assertStringStartsWith('<info>Migration "\MyNamespace\MyFirstMigration" created in "' . realpath($createMigrationDir), $messages[0][1]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);

        rmdir($createMigrationDir);
    }

    public function testCreateMigrationInNewDirectoryWithTabIndent(): void
    {
        $createMigrationDir = __DIR__ . '/../../testing_migrations/new';
        $this->assertFalse(is_dir($createMigrationDir));
        mkdir($createMigrationDir);
        $this->assertTrue(is_dir($createMigrationDir));

        $configuration = $this->configuration;
        $configuration['migration_dirs']['create'] = $createMigrationDir;

        $initCommand = new InitCommand();
        $input = $this->createInput();
        $initCommand->setConfig($configuration);
        $initCommand->run($input, new Output());

        $createFiles = Finder::create()->files()->in($createMigrationDir);
        $this->assertCount(0, $createFiles);

        $command = new CreateCommand();
        $command->setConfig($configuration);
        $this->input->setArgument('migration', '\MyNamespace\MyFirstMigration');
        $this->input->setArgument('dir', 'create');
        $this->input->setOption('indent', 'tab');
        $command->run($this->input, $this->output);

        $createFiles = Finder::create()->files()->in($createMigrationDir);
        $this->assertCount(1, $createFiles);
        foreach ($createFiles as $createFile) {
            $filePath = (string)$createFile;

            $migrationContent = file_get_contents($filePath);
            $this->assertStringStartsWith('<?php

declare(strict_types=1);', $migrationContent);
            $this->assertStringContainsString("\t", $migrationContent);
            $this->assertStringNotContainsString("    ", $migrationContent);

            $classNameCreator = new ClassNameCreator($filePath);
            $this->assertEquals('\MyNamespace\MyFirstMigration', $classNameCreator->getClassName());
            unlink($filePath);
        }

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);

        $this->assertCount(5, $messages[0]);
        $this->assertStringStartsWith('<info>Migration "\MyNamespace\MyFirstMigration" created in "' . realpath($createMigrationDir), $messages[0][1]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);

        rmdir($createMigrationDir);
    }
}
