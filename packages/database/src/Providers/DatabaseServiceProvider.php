<?php

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
        if ($this->isRunningInConsole()) {
            $this->registerCommands([
//                DiffCommand::class,
                MigrateCommand::class,
                StatusCommand::class,
                CleanupCommand::class,
                DumpCommand::class,
                CreateCommand::class,
                RollbackCommand::class,
                InitCommand::class,
            ]);
        }

        if (class_exists('Ody\DB\Eloquent')) {
            \Ody\DB\Eloquent::boot(
                config('database.environments')[config('app.environment', 'local')]
            );
        }
    }
}