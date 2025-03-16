<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\RollbackCommand;

use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\RollbackCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;

abstract class RollbackCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new RollbackCommand();
        $this->assertEquals('migrations:rollback', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new RollbackCommand('my_rollback');
        $this->assertEquals('my_rollback', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new RollbackCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new RollbackCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testNothingToRollbackWithoutInitializing(): void
    {
        $command = new RollbackCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertEquals('<info>Nothing to rollback</info>' . "\n", $messages[0][6]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testNothingToRollbackWithInitializing(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Nothing to rollback</info>' . "\n", $messages[0][1]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testMigrateAndRollback(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(6, $messages[0]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);

        $input = $this->createInput();
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(6, $messages[0]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testRollbackAll(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $input->setOption('all', true);
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);

        $input = $this->createInput();
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Nothing to rollback</info>' . "\n", $messages[0][1]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testRollbackDir(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $input->setOption('dir', ['migrations']);
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(6, $messages[0]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);

        $input = $this->createInput();
        $input->setOption('dir', ['migrations']);
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(6, $messages[0]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testRollbackUnknownDir(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $input->setOption('dir', ['xxx']);
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Directory "xxx" doesn\'t exist');
        $command->run($input, $output);
    }

    public function testDryRun(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $input = $this->createInput();
        $input->setOption('dry', true);
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $dryMessages = $output->getMessages();
        $dryQueries = $dryMessages[OutputInterface::VERBOSITY_DEBUG];

        $this->assertTrue(is_array($dryMessages));
        $this->assertArrayHasKey(0, $dryMessages);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $dryMessages);

        $input = $this->createInput();
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $messages = $output->getMessages();
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
        $this->assertEquals($dryQueries, $messages[OutputInterface::VERBOSITY_DEBUG]);
    }

    public function testDryRunWithJsonOutput(): void
    {
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $input = $this->createInput();
        $input->setOption('dry', true);
        $input->setOption('output-format', 'json');
        $output = new Output();
        $command = new RollbackCommand();
        $command->setConfig($this->configuration);
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
