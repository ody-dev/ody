<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Container;

/**
 * Helper class for container service registration
 */
class ContainerHelper
{
    /**
     * Configure the application container with basic services
     *
     * @param Container $container
     * @param array $config Application configuration
     * @return Container
     */
    public static function configureContainer(Container $container, array $config = []): Container
    {
        // Register configuration
        $container->instance('config', $config);

        // Register db connection if configured
        if (isset($config['database'])) {
            $container->singleton('db', function ($container) {
                $config = $container->make('config');
                $dbConfig = $config['database'];

                // This is just a placeholder - would use your actual DB connection class
                return new \PDO(
                    "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
                    $dbConfig['username'],
                    $dbConfig['password']
                );
            });
        }

        // Register controller namespace for auto-resolution
        $container->alias('App\\Controllers\\', 'controllers');

        return $container;
    }

    /**
     * Helper method to register all controller classes in a directory
     *
     * @param Container $container
     * @param string $controllerDirectory
     * @return void
     */
    public static function registerControllers(Container $container, string $controllerDirectory): void
    {
        if (!is_dir($controllerDirectory)) {
            return;
        }

        $files = scandir($controllerDirectory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_file($controllerDirectory . '/' . $file) && strpos($file, '.php') !== false) {
                $className = 'App\\Controllers\\' . str_replace('.php', '', $file);

                if (class_exists($className)) {
                    $container->singleton($className, function ($container) use ($className) {
                        return $container->build($className);
                    });
                }
            }
        }
    }
}