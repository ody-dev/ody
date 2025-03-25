<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Command;

use Ody\DB\Migrations\Migration\Init\Init;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends AbstractCommand
{
    public function __construct(string $name = 'migrations:init')
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription('Initialize migrations');
        parent::configure();
    }

    protected function runCommand(): void
    {
        $filename = __DIR__ . '/../Migration/Init/Init.php';
        require_once $filename;
        $migration = new Init($this->adapter, $this->getConfig()->getLogTableName());
        $migration->migrate();

        $executedQueries = $migration->getExecutedQueries();
        $this->writeln(['', '<info>Ody migrations initialized</info>']);
        $this->writeln(['Executed queries:'], OutputInterface::VERBOSITY_DEBUG);
        $this->writeln($executedQueries, OutputInterface::VERBOSITY_DEBUG);

        $this->outputData['message'] = 'Ody migrations initialized';

        if ($this->output->getVerbosity() === OutputInterface::VERBOSITY_DEBUG) {
            $this->outputData['executed_queries'] = $executedQueries;
        }
    }
}
