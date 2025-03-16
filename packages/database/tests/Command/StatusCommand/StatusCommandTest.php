<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command\StatusCommand;

use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\StatusCommand;
use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Tests\Command\BaseCommandTest;
use Ody\DB\Tests\Mock\Command\Output;
use Symfony\Component\Console\Output\OutputInterface;

abstract class StatusCommandTest extends BaseCommandTest
{
    public function testDefaultName(): void
    {
        $command = new StatusCommand();
        $this->assertEquals('migrations:status', $command->getName());
    }

    public function testCustomName(): void
    {
        $command = new StatusCommand('my_status');
        $this->assertEquals('my_status', $command->getName());
    }

//    public function testMissingDefaultConfig(): void
//    {
//        $command = new StatusCommand();
//        $this->expectException(ConfigException::class);
//        $this->expectExceptionMessage('No configuration file exists. Create database.php in your project config folder.');
//        $command->run($this->input, $this->output);
//    }

    public function testUserConfigFileNotFound(): void
    {
        $command = new StatusCommand();
        $this->input->setOption('config', 'xyz.neon');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file "xyz.neon" doesn\'t exist.');
        $command->run($this->input, $this->output);
    }

    public function testStatusWithoutInitializing(): void
    {
        $command = new StatusCommand();
        $this->input->setOption('config', __DIR__ . '/../../testing_migrations/config/database.php');
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<info>Ody migrations initialized</info>' . "\n", $messages[0][1]);
        $this->assertEquals('<comment>Executed migrations</comment>' . "\n", $messages[0][6]);
        $this->assertEquals('<info>No executed migrations</info>' . "\n", $messages[0][7]);
        $this->assertEquals('<comment>Migrations to execute</comment>' . "\n", $messages[0][9]);
        $this->assertEquals('|<info> Migration datetime </info>|<info> Class name                                   </info>|' . "\n", $messages[0][11]);
        $this->assertArrayHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testStatusWithInitializing(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new InitCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new StatusCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<comment>Executed migrations</comment>' . "\n", $messages[0][1]);
        $this->assertEquals('<info>No executed migrations</info>' . "\n", $messages[0][2]);
        $this->assertEquals('<comment>Migrations to execute</comment>' . "\n", $messages[0][4]);
        $this->assertEquals('|<info> Migration datetime </info>|<info> Class name                                   </info>|' . "\n", $messages[0][6]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testStatusAfterOneExecutedMigration(): void
    {
        $input = $this->createInput();
        $input->setOption('first', true);
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new StatusCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<comment>Executed migrations</comment>' . "\n", $messages[0][1]);
        $this->assertEquals('|<info> Migration datetime </info>|<info> Class name                    </info>|<info> Executed at         </info>|' . "\n", $messages[0][3]);
        $this->assertEquals('<comment>Migrations to execute</comment>' . "\n", $messages[0][8]);
        $this->assertEquals('|<info> Migration datetime </info>|<info> Class name                                   </info>|' . "\n", $messages[0][10]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testStatusAfterAllMigrationsExecuted(): void
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

        $executedMigrationsCount = (count($output->getMessages(0)) - 3) / 3;

        $command = new StatusCommand();
        $command->setConfig($this->configuration);
        $command->run($this->input, $this->output);

        $messages = $this->output->getMessages();

        $this->assertTrue(is_array($messages));
        $this->assertArrayHasKey(0, $messages);
        $this->assertEquals('<comment>Executed migrations</comment>' . "\n", $messages[0][1]);
        $this->assertEquals('|<info> Migration datetime </info>|<info> Class name                                   </info>|<info> Executed at         </info>|' . "\n", $messages[0][3]);
        $this->assertEquals('<comment>Migrations to execute</comment>' . "\n", $messages[0][7 + $executedMigrationsCount]);
        $this->assertEquals('<info>No migrations to execute</info>' . "\n", $messages[0][8 + $executedMigrationsCount]);
        $this->assertArrayNotHasKey(OutputInterface::VERBOSITY_DEBUG, $messages);
    }

    public function testStatusWithJsonOutputAfterOneExecutedMigration(): void
    {
        $input = $this->createInput();
        $input->setOption('first', true);
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new StatusCommand();
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
        $this->assertArrayHasKey('migrations_to_execute', $message);
        $this->assertNotEmpty($message['executed_migrations']);
        $this->assertNotEmpty($message['migrations_to_execute']);
    }

    public function testStatusWithJsonOutputAfterAllMigrationsExecuted(): void
    {
        $input = $this->createInput();
        $output = new Output();
        $command = new MigrateCommand();
        $command->setConfig($this->configuration);
        $command->run($input, $output);

        $command = new StatusCommand();
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
        $this->assertArrayHasKey('migrations_to_execute', $message);
        $this->assertNotEmpty($message['executed_migrations']);
        $this->assertEmpty($message['migrations_to_execute']);
    }
}
