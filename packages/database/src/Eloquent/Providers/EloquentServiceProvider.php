<?php
declare(strict_types=1);
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Eloquent\Providers;

use Ody\DB\Eloquent\Eloquent;
use Ody\DB\Migrations\Command\CleanupCommand;
use Ody\DB\Migrations\Command\CreateCommand;
use Ody\DB\Migrations\Command\DumpCommand;
use Ody\DB\Migrations\Command\InitCommand;
use Ody\DB\Migrations\Command\MigrateCommand;
use Ody\DB\Migrations\Command\RollbackCommand;
use Ody\DB\Migrations\Command\StatusCommand;
use Ody\Foundation\Providers\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->container);

        $this->container->singleton('db', function ($app) {
            return new \Ody\DB\Eloquent\Facades\DB();
        });
    }

    public function boot(): void
    {
        // Publish configuration
//        $this->publishes([
//            __DIR__ . '/../../config/database.php' => 'database.php'
//        ], 'ody/database');

        Eloquent::boot(
            config('database.environments')[config('app.environment', 'local')]
        );
    }
}