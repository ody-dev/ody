<?php

namespace Ody\Auth\Middleware;

use Ody\Auth\AuthManager;
use Ody\Container\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AttachUserToRequestResolver
{
    /**
     * Resolve the attach user middleware with parameters.
     *
     * @param Container $container The application container
     * @param array $parameters Middleware parameters
     * @return AttachUserToRequest
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

        // Create the middleware with dependencies
        return new AttachUserToRequest($auth, $logger, $parameters);
    }
}