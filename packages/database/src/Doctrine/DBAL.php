<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\DriverManager;

/**
 * DBAL Module
 */
class DBAL
{
    /**
     * Whether the DBAL module has been booted
     *
     * @var bool
     */
    protected static bool $booted = false;

    /**
     * Boot the DBAL module
     *
     * @param array $config
     * @return void
     */
    public static function boot(array $config): void
    {
        // Only boot once
        if (self::$booted) {
            logger()->debug("DBAL already booted, skipping initialization");
            return;
        }

        logger()->info("Booting Doctrine DBAL");

        // Initialize the connection resolver
        ConnectionResolver::initialize();

        // Patch Doctrine's DriverManager to use our connection resolver
        self::patchDriverManager();

        // Pre-initialize the pool if pooling is enabled
        if (config('database.enable_connection_pool', false) && extension_loaded('swoole')) {
            // Create a pool for the default connection
            self::initPool($config);
        }

        self::$booted = true;
    }

    /**
     * Initialize a connection pool
     *
     * @param array $config
     * @param string $name
     * @param int $poolSize
     * @return void
     */
    public static function initPool(array $config, string $name = 'default', int $poolSize = 64): void
    {
        // Add pooling parameters
        $config['connection_name'] = $name;
        $config['use_pooling'] = true;
        $config['pool_size'] = $poolSize;

        // Get a pool setup but don't actually get a connection yet
        ConnectionResolver::getPool($config);
    }

    /**
     * Patch Doctrine's DriverManager to use our connection resolver
     *
     * @return void
     */
    protected static function patchDriverManager(): void
    {
        // Store the original getConnection method
        if (!method_exists(DriverManager::class, 'getConnectionOrig')) {
            $getConnectionReflection = new \ReflectionMethod(DriverManager::class, 'getConnection');
            $getConnectionFunc = $getConnectionReflection->getClosure();

            // Add the original method as a static method
            DriverManager::getConnectionOrig = $getConnectionFunc;

            // Override the getConnection method to check for pooling
            DriverManager::getConnection = function(array $params, $config = null, $eventManager = null) {
                // Check if we should use a pooled connection
                $usePooling = ($params['use_pooling'] ?? false) && extension_loaded('swoole');

                if ($usePooling && Coroutine::getCid() >= 0) {
                    // Return a pooled connection
                    return ConnectionResolver::resolveConnection($params, $config, $eventManager);
                }

                // Otherwise use the original factory
                return DriverManager::getConnectionOrig($params, $config, $eventManager);
            };
        }
    }
}