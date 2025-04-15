<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

use Ody\Container\Container;
use Ody\Logger\Log;
use Ody\Support\Config;
use Ody\Support\Env;

if (!function_exists('app')) {
    /**
     * Get the application container instance or resolve a service from it.
     *
     * @param string|null $abstract Service to resolve
     * @param array $parameters Parameters to pass to the resolver
     * @return mixed|Container
     */
    function app($abstract = null, array $parameters = [])
    {
        $container = Container::getInstance();

        if (is_null($abstract)) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed|Config
     */
    function config($key = null, $default = null) {
        $config = app(Config::class);

        if (is_null($key)) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable.
     *
     * @param string $key Environment variable key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    function env($key, $default = null)
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the application base path.
     *
     * @param string $path Path to append to the base path
     * @return string
     */
    function base_path($path = '')
    {
        $basePath = defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 3);
        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path.
     *
     * @param string $path Path to append to the storage path
     * @return string
     */
    function storage_path($path = '')
    {
        return base_path('storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the config path.
     *
     * @param string $path Path to append to the config path
     * @return string
     */
    function config_path($path = '')
    {
        return base_path('config') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('route_path')) {
    /**
     * Get the routes' path.
     *
     * @param string $path Path to append to the routes path
     * @return string
     */
    function route_path($path = '')
    {
        return base_path('routes') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed $target
     * @param string|array|int|null $key
     * @param mixed $default
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message to the application logs.
     *
     * @param mixed $message
     * @param array $context
     * @return Logger
     */
    function logger($message = null, array $context = [])
    {
        $logger = app('logger');

        if (is_null($message)) {
            return $logger;
        }

        return $logger->info($message, $context);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the config path.
     *
     * @param string $path Path to append to the config path
     * @return string
     */
    function config_path($path = '')
    {
        return base_path('config') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path.
     *
     * @param string $path Path to append to the database path
     * @return string
     */
    function database_path($path = '')
    {
        return base_path('database') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}

//if (!function_exists('logger')) {
//    /**
//     * Get a logger instance or log a message
//     *
//     * @param mixed $message
//     * @param array $context
//     * @param string|null $channel
//     * @return LoggerInterface
//     */
//    function logger($message = null, array $context = [], ?string $channel = null): LoggerInterface
//    {
//        /** @var LogManager $logManager */
//        $logManager = app('log');
//        $logger = $channel ? $logManager->channel($channel) : $logManager->channel();
//
//        if (is_null($message)) {
//            return $logger;
//        }
//
//        return $logger->info($message, $context);
//    }
//}