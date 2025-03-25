<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Providers;

use Ody\DB\Migrations\Command\CleanupCommand;
use Ody\DB\Migrations\Command\CreateCommand;
use Ody\DB\Migrations\Command\DumpCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\RollbackCommand;
use Ody\DB\Migrations\Command\StatusCommand;
use Ody\Foundation\Providers\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/database.php' => 'database.php'
        ], 'ody/database');

        if ($this->isRunningInConsole()) {
            $this->registerCommands([
                MigrateCommand::class,
                StatusCommand::class,
                CleanupCommand::class,
                DumpCommand::class,
                CreateCommand::class,
                RollbackCommand::class,
                InitCommand::class,
            ]);
        }
    }
}