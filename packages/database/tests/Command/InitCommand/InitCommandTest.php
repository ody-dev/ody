<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Command\InitCommand;

use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\WrongCommandException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Symfony\Component\Console\Output\OutputInterface;

abstract class InitCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new InitCommand();
        $this->assertEquals('migrations:init', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new InitCommand('my_init');
        $this->assertEquals('my_init', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new InitCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new InitCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testUserConfigFile(): void
    {
        $command = new InitCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(5, $messages[0]);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
        $this->assertTrue(count($messages[OutputInterface::VERBOSITY_DEBUG]) > 0);
    }

    public function testDefaultConfig(): void
    {
        $command = new InitCommand();
        $command->run($this->input, $this->output);
        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(5, $messages[0]);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
        $this->assertTrue(count($messages[OutputInterface::VERBOSITY_DEBUG]) > 0);
    }

    public function testSetCustomConfig(): void
    {
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertCount(5, $messages[0]);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
        $this->assertTrue(count($messages[OutputInterface::VERBOSITY_DEBUG]) > 0);
    }

    public function testSetCustomConfigWithJsonOutput(): void
    {
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $this->input->setOption('output-format', 'json');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages(0);

        $this->assertTrue(is_array($messages));
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey(0, $messages);
        $this->assertJson($messages[0]);

        $message = json_decode($messages[0], true);
        $this->assertArrayHasKey('message', $message);
        $this->assertArrayNotHasKey('executed_queries', $message);
        $this->assertArrayHasKey('execution_time', $message);
    }

    public function testSetCustomConfigWithJsonOutputAndVerbosityDebug(): void
    {
        $command = new InitCommand();
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
        $this->assertArrayHasKey('message', $message);
        $this->assertArrayHasKey('executed_queries', $message);
        $this->assertArrayHasKey('execution_time', $message);
    }

    public function testMultipleInitialization(): void
    {
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $this->expectException(WrongCommandException::class);
        $this->expectExceptionMessage('Phoenix was already initialized, run migrate or rollback command now.');
        $command->run($this->input, $this->output);
    }
}
