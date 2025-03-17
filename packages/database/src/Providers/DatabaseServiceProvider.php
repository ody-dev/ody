<?php

namespace Ody\DB\Providers;

use Ody\Foundation\Providers\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
//        if ($this->container->get('runningInConsole')) {
//            $this->commands = [
//                DiffCommand::class,
//                MigrateCommand::class,
//                StatusCommand::class,
//                CleanupCommand::class,
//                DumpCommand::class,
//                CreateCommand::class,
//                RollbackCommand::class,
//                InitCommand::class,
//            ];
//        }

        if (class_exists('Ody\DB\Eloquent')) {
            \Ody\DB\Eloquent::boot(
                config('database.environments')[config('app.environment', 'local')]
            );
        }
    }
}