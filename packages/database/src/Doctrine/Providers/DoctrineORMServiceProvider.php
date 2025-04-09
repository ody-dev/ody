<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine\Providers;

use Doctrine\ORM\EntityManagerInterface;
use Ody\DB\Doctrine\EntityManagerFactory;
use Ody\Foundation\Providers\ServiceProvider;

class DoctrineORMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register EntityManager factory
        $this->container->singleton('db.orm.factory', function ($app) {
            return new EntityManagerFactory($app, $app->get('db.dbal'));
        });

        // Register default EntityManager instance
        $this->container->singleton('db.orm', function ($app) {
            $factory = $app->get('db.orm.factory');
            return $factory->create();
        });

        $this->container->alias('db.orm', EntityManagerInterface::class);

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
            __DIR__ . '/../../../config/doctrine.php' => 'doctrine.php'
        ], 'ody/doctrine');

        // Register console commands for development/testing environment
        if ($this->isRunningInConsole()) {
            $this->registerORMCommands();
        }
    }

    protected function registerORMCommands(): void
    {
        // Register Doctrine ORM console commands
        $this->registerCommands([
            // TODO: implement commands
        ]);
    }
}