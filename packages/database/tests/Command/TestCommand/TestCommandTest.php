<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Command\TestCommand;

use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\TestCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;

abstract class TestCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new TestCommand();
        $this->assertEquals('test', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new TestCommand('my_test');
        $this->assertEquals('my_test', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new TestCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new TestCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testTestWithoutInitializing(): void
    {
        $command = new TestCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertEquals('<comment>Test started...</comment>' . "\n", $messages[0][6]);
        $this->assertEquals('<comment>Test finished successfully</comment>' . "\n", $messages[0][17]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testTestWithoutInitializingWithCleanup(): void
    {
        $command = new TestCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $this->input->setOption('cleanup', true);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertEquals('<comment>Test started...</comment>' . "\n", $messages[0][6]);
        $this->assertEquals('<comment>Test finished successfully</comment>' . "\n", $messages[0][20]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testTestWithInitializing(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new TestCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<comment>Test started...</comment>' . "\n", $messages[0][1]);
        $this->assertEquals('<comment>Test finished successfully</comment>' . "\n", $messages[0][12]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testTestAfterAllMigrationsExecuted(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $input = $this->createInput();
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new TestCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<comment>Test started...</comment>' . "\n", $messages[0][1]);
        $this->assertEquals('<comment>Nothing to test</comment>' . "\n", $messages[0][3]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testTestWithJsonOutput(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new TestCommand();
        $command->setConfig($this->configuration);
        $this->input->setOption('output-format', 'json');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);
        $this->assertArrayHasKey('executed_migrations', $message);
        $this->assertNotEmpty($message['executed_migrations']);

        foreach ($message['executed_migrations'] as $executedMigration) {
            $this->assertArrayHasKey('classname', $executedMigration);
            $this->assertArrayHasKey('type', $executedMigration);
            $this->assertArrayHasKey('execution_time', $executedMigration);
            $this->assertArrayNotHasKey('executed_queries', $executedMigration);
        }
    }

    public function testTestWithJsonOutputVeryVerbose(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new TestCommand();
        $command->setConfig($this->configuration);
        $this->input->setOption('output-format', 'json');
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);
        $this->assertArrayHasKey('executed_migrations', $message);
        $this->assertNotEmpty($message['executed_migrations']);

        foreach ($message['executed_migrations'] as $executedMigration) {
            $this->assertArrayHasKey('classname', $executedMigration);
            $this->assertArrayHasKey('type', $executedMigration);
            $this->assertArrayHasKey('execution_time', $executedMigration);
            $this->assertArrayHasKey('executed_queries', $executedMigration);
        }
    }
}
