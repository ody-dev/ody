<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

class Eloquent
{
    /**
     * @var Capsule
     */
    protected static $capsule;

    /**
     * Whether Eloquent has been booted
     *
     * @var bool
     */
    protected static $booted = false;

    /**
     * Boot Eloquent with the given configuration
     *
     * @param array $config
     * @return void
     */
    public static function boot($config): void
    {
        // Only boot once
        if (self::$booted) {
            logger()->debug("Eloquent already booted, skipping initialization");
            return;
        }

        logger()->info("Booting Eloquent");

        static::$capsule = new Capsule;

        // Convert config to Eloquent format
        $eloquentConfig = (new Eloquent())->setConfig($config);
        static::$capsule->addConnection($eloquentConfig);

        // If Swoole coroutines are available and pool is enabled, register resolver
        if (config('database.enable_connection_pool', false) && extension_loaded('swoole')) {
            // Register the custom connection resolver for MySQL
            Connection::resolverFor('mysql', function ($pdo, $database, $prefix, $config) {
                // Use our factory to create connections from the pool
                return ConnectionFactory::make($config);
            });
        }

        static::$capsule->setAsGlobal();
        static::$capsule->bootEloquent();

        // Set timezone for timestamps etc
        date_default_timezone_set('UTC');

        self::$booted = true;
    }

    /**
     * Get the Capsule manager instance
     *
     * @return Capsule
     */
    public static function getCapsule()
    {
        return static::$capsule;
    }

    /**
     * Format the configuration array for Eloquent
     *
     * @param array $config
     * @return array
     */
    public function setConfig(array $config): array
    {
        // Convert adapter to driver for Eloquent
        $config['driver'] = $config['adapter'] ?? 'mysql';
        $config['database'] = $config['db_name'] ?? '';

        // Add pooling configuration if needed
        if (config('database.enable_connection_pool', false)) {
            $config['pool_size'] = config('database.pool_size', 32);
        }

        return $config;
    }
}