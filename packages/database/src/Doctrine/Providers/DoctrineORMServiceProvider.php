<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Providers;

use Doctrine\Common\Cache\ArrayCache;
use Ody\DB\Doctrine\EntityManagerFactory;
use Ody\DB\Doctrine\Facades\ORM;
use Ody\Foundation\Providers\ServiceProvider;

class DoctrineORMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register EntityManager factory
        $this->container->singleton('db.orm.factory', function ($app) {
            return new EntityManagerFactory($app);
        });

        // Register default EntityManager instance
        $this->container->singleton('db.orm', function ($app) {
            $factory = $app->get('db.orm.factory');
            return $factory->create();
        });

        // Register entity manager resolver
        $this->container->singleton('db.orm.resolver', function ($app) {
            return function ($name = null) use ($app) {
                if ($name === null) {
                    return $app->get('db.orm');
                }

                $factory = $app->get('db.orm.factory');
                return $factory->create($name);
            };
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/doctrine.php' => 'doctrine.php'
        ], 'ody/doctrine');

        // Initialize ORM facade
        if (class_exists('Ody\DB\Doctrine\Facades\ORM')) {
            ORM::setResolver($this->container->get('db.orm.resolver'));
        }

        // Register console commands for development/testing environment
        if ($this->isRunningInConsole()) {
            $this->registerORMCommands();
        }
    }

    protected function registerORMCommands(): void
    {
        // Register Doctrine ORM console commands
        $this->registerCommands([
            // Add your Doctrine ORM command classes here
            // These will likely include schema creation, validation, etc.
        ]);
    }
}