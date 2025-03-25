<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Providers;

use Ody\DB\ConnectionFactory;
use Ody\DB\Eloquent;
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
        if (class_exists('Illuminate\\Support\\Facades\\Facade')) {
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->container);
        }

        $this->container->singleton('db', function ($app) {
            return new \Ody\DB\Facades\DB();
        });

//        $this->container->singleton(DB::class, function ($app) {
//            return new \Ody\DB\Facades\DB();
//        });
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

            return;
        }

        // Check if we should use Swoole pooling
        $usePooling = config('database.enable_connection_pool', false);

        // Get database configuration
        $dbConfig = config('database.environments')[config('app.environment', 'local')];

        // Boot Eloquent with the appropriate configuration
        Eloquent::boot($dbConfig);

        // If using connection pooling, pre-initialize the default pool
        if ($usePooling) {
            logger()->info("Pre-initializing connection pool");

            // For the default connection, initialize the pool without actually borrowing a connection
            ConnectionFactory::getPool($dbConfig, 'default');
        }
    }
}