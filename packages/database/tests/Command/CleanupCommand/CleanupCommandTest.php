<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Command\CleanupCommand;

use Ody\DB\Migrations\Command\CleanupCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CleanupCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new CleanupCommand();
        $this->assertEquals('migrations:cleanup', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new CleanupCommand('my_cleanup');
        $this->assertEquals('my_cleanup', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new CleanupCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new CleanupCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testUserConfigFile(): void
    {
        $initCommand = new InitCommand();
        $input = $this->createInput();
        $input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $initCommand->run($input, new Output());

        $command = new CleanupCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(6, $messages[0]);
        $this->assertEquals('<info>Ody cleaned</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
        $this->assertTrue(count($messages[OutputInterface::VERBOSITY_DEBUG]) > 0);
    }

    public function testUserConfigFileAndJsonOutput(): void
    {
        $initCommand = new InitCommand();
        $input = $this->createInput();
        $input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $initCommand->run($input, new Output());

        $command = new CleanupCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $this->input->setOption('output-format', 'json');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);

        $this->assertArrayHasKey('message', $message);
        $this->assertEquals('Ody cleaned', $message['message']);
        $this->assertArrayNotHasKey('executed_migrations', $message);
        $this->assertArrayHasKey('execution_time', $message);
    }

    public function testUserConfigFileAndJsonOutputAndDebugVerbosity(): void
    {
        $initCommand = new InitCommand();
        $input = $this->createInput();
        $input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $initCommand->run($input, new Output());

        $command = new CleanupCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $this->input->setOption('output-format', 'json');
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);

        $this->assertArrayHasKey('message', $message);
        $this->assertEquals('Ody cleaned', $message['message']);
        $this->assertArrayHasKey('executed_migrations', $message);
        $this->assertArrayHasKey('execution_time', $message);
    }
}
