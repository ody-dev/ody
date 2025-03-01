<?php

namespace Ody\DB\ServiceProviders;

use Ody\Core\Foundation\Providers\ServiceProvider;
use Ody\DB\Migrations\Command\CleanupCommand;
use Ody\DB\Migrations\Command\CreateCommand;
use Ody\DB\Migrations\Command\DiffCommand;
use Ody\DB\Migrations\Command\DumpCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\RollbackCommand;
use Ody\DB\Migrations\Command\StatusCommand;

class DatabaseServiceProvider extends ServiceProvider
{
    public array $commands = [
        DiffCommand::class,
        MigrateCommand::class,
        StatusCommand::class,
        CleanupCommand::class,
        DumpCommand::class,
        CreateCommand::class,
        RollbackCommand::class,
        InitCommand::class,
    ];

    public function register()
    {

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands = [
                DiffCommand::class,
                MigrateCommand::class,
                StatusCommand::class,
                CleanupCommand::class,
                DumpCommand::class,
                CreateCommand::class,
                RollbackCommand::class,
                InitCommand::class,
            ];
        }

        if (class_exists('Ody\DB\Eloquent')) {
            \Ody\DB\Eloquent::boot(
                config('database.environments')[config('app.environment', 'local')]
            );
        }
    }
}