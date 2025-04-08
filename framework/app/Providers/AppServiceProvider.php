<?php

namespace App\Providers;

use App\Repositories\UserRepository;
use Ody\Foundation\Providers\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register user repository
        $this->container->bind(UserRepository::class);
    }

    public function boot(): void
    {
        // Boot logic
    }
}