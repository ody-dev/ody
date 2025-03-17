<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Ody\Container\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AuthenticateResolver
{
    /**
     * Resolve the authenticate middleware with parameters.
     *
     * @param Container $container The application container
     * @param array $parameters Middleware parameters
     * @return Authenticate
     */
    public static function resolve(Container $container, array $parameters = [])
    {
        // Get the auth manager from the container
        $auth = $container->has(AuthManager::class)
            ? $container->get(AuthManager::class)
            : $container->get('auth');

        // Get logger if available
        $logger = $container->has(LoggerInterface::class)
            ? $container->get(LoggerInterface::class)
            : new NullLogger();

        // Get the default guard from parameters or config
        $defaultGuard = null;
        if (!empty($parameters)) {
            $defaultGuard = implode(',', $parameters);
        }

        // Create the middleware with dependencies
        return new Authenticate($auth, $logger, $defaultGuard);
    }
}