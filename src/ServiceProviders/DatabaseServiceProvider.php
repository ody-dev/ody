<?php

namespace Ody\DB\ServiceProviders;

use Ody\Core\ServiceProviders\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        if (class_exists('Ody\DB\Eloquent')) {
            \Ody\DB\Eloquent::boot(
                config('database.environments')[config('app.environment', 'local')]
            );
        }
    }
}