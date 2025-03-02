<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Command;

use Ody\DB\Migrations\Config\Parser\PhpConfigParser;
use Ody\DB\Tests\Helpers\Adapter\CleanupInterface;
use Ody\DB\Tests\Mock\Command\Input;
use Ody\DB\Tests\Mock\Command\Output;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

abstract class BaseCommandTest extends TestCase
{
    protected Input $input;

    protected Output $output;

    protected array $configuration;

    protected function setUp(): void
    {
        // delete root config
        $rootConfigPath = __DIR__ . '/../../database.php';
        if (file_exists($rootConfigPath)) {
            unlink($rootConfigPath);
        }

        // delete additional migration dir
        $newMigrationDir = __DIR__ . '/../testing_migrations/new';
        if (file_exists($newMigrationDir)) {
            $newFiles = Finder::create()->files()->in($newMigrationDir);
            foreach ($newFiles as $newFile) {
                $filePath = (string)$newFile;
                unlink($filePath);
            }
            rmdir($newMigrationDir);
        }

        $adapter = $this->getAdapter();
        $adapter->cleanupDatabase();

        $parser = new PhpConfigParser();
        $this->configuration = $parser->parse(__DIR__ . '/../testing_migrations/config/database.php');

        $this->input = $this->createInput();
        $this->output = new Output();
    }

    protected function createInput(): Input
    {
        $input = new Input();
        $input->setOption('environment', $this->getEnvironment());
        return $input;
    }

    abstract protected function getEnvironment(): string;

    abstract protected function getAdapter(): CleanupInterface;
}
