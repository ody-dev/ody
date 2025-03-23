<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Command\MigrateCommand;

use Ody\DB\Migrations\Command\CleanupCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MigrateCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new MigrateCommand();
        $this->assertEquals('migrations:migrate', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new MigrateCommand('my_migrate');
        $this->assertEquals('my_migrate', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new MigrateCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new MigrateCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testUserConfigFile(): void
    {
        $command = new MigrateCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testSetCustomConfig(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testMultipleMigration(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $output = new Output();
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Nothing to migrate</info>' . "\n", $messages[0][1]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testOnlyFirstMigration(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);

        $input = $this->createInput();
        $input->setOption('first', true);
        $output = new Output();
        $command->run($input, $output);
        $messagesFirst = $output->getMessages();

        $command = new CleanupCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $input = $this->createInput();
        $output = new Output();
        $command->run($input, $output);
        $messagesAll = $output->getMessages();

        $this->assertGreaterThan(count($messagesFirst[0]), count($messagesAll[0]));
    }

    public function testMigrateDir(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);

        $input = $this->createInput();
        $input->setOption('first', true);
        $input->setOption('dir', ['migrations']);
        $output = new Output();
        $command->run($input, $output);
        $messagesFirst = $output->getMessages();

        $command = new CleanupCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $input = $this->createInput();
        $input->setOption('dir', ['migrations']);
        $output = new Output();
        $command->run($input, $output);
        $messagesAll = $output->getMessages();

        $this->assertGreaterThan(count($messagesFirst[0]), count($messagesAll[0]));
    }

    public function testMigrateUnknownDir(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);

        $input = $this->createInput();
        $input->setOption('dir', ['xxx']);
        $output = new Output();
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Directory "xxx" doesn\'t exist');
        $command->run($input, $output);
    }

    public function testDryRun(): void
    {
        $initCommand = new InitCommand();
        $initCommand->setConfig($this->configuration);
        $initCommand->run($this->createInput(), new Output());

        $input = $this->createInput();
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $input->setOption('dry', true);
        $input->setOption('first', true);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $dryQueries = $messages[OutputInterface::VERBOSITY_DEBUG];

        $input = $this->createInput();
        $output = new Output();
        $input->setOption('first', true);
        $command->run($input, $output);

        $realRunMessages = $output->getMessages();
        $this->assertEquals($dryQueries, $realRunMessages[OutputInterface::VERBOSITY_DEBUG]);
    }

    public function testDryRunWithJsonOutput(): void
    {
        $initCommand = new InitCommand();
        $initCommand->setConfig($this->configuration);
        $initCommand->run($this->createInput(), new Output());

        $input = $this->createInput();
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $input->setOption('dry', true);
        $input->setOption('first', true);
        $input->setOption('output-format', 'json');
        $command->run($input, $output);

        $messages = $output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);

        $this->assertArrayHasKey('executed_migrations', $message);
        $this->assertArrayHasKey('execution_time', $message);
        $this->assertNotEmpty($message['executed_migrations']);
        $this->assertNotEmpty($message['execution_time']);
        foreach ($message['executed_migrations'] as $executedMigration) {
            $this->assertArrayHasKey('classname', $executedMigration);
            $this->assertArrayHasKey('execution_time', $executedMigration);
            $this->assertArrayHasKey('executed_queries', $executedMigration);
        }
    }
}
