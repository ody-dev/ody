<?php

namespace Ody\Auth;

/**
 * Auth Factory
 * Creates appropriate auth provider based on configuration
 */
class AuthFactory
{
    /**
     * Create an auth provider based on configuration
     *
     * @param array $config Configuration array
     * @return AuthProviderInterface
     */
    public static function createFromConfig(array $config)
    {
        if ($config['provider'] === 'direct') {
            return self::createDirectProvider(
                $config['userRepository'],
                $config['jwtKey'],
                $config['tokenExpiry'] ?? 3600,
                $config['refreshTokenExpiry'] ?? 2592000
            );
        } elseif ($config['provider'] === 'remote') {
            return self::createRemoteProvider(
                $config['authServiceHost'],
                $config['authServicePort'],
                $config['serviceId'],
                $config['serviceSecret']
            );
        }

        throw new \InvalidArgumentException('Invalid auth provider type');
    }

    /**
     * Create a direct auth provider
     *
     * @param mixed $userRepository User repository (Eloquent or Doctrine)
     * @param string $jwtKey JWT secret key
     * @param int $tokenExpiry Token expiry in seconds
     * @param int $refreshTokenExpiry Refresh token expiry in seconds
     * @return DirectAuthProvider
     */
    public static function createDirectProvider($userRepository, string $jwtKey, int $tokenExpiry = 3600, int $refreshTokenExpiry = 2592000)
    {
        return new DirectAuthProvider($userRepository, $jwtKey, $tokenExpiry, $refreshTokenExpiry);
    }

    /**
     * Create a remote auth provider
     *
     * @param string $authServiceHost Auth service hostname
     * @param int $authServicePort Auth service port
     * @param string $serviceId Service identifier
     * @param string $serviceSecret Service secret
     * @return RemoteAuthProvider
     */
    public static function createRemoteProvider(string $authServiceHost, int $authServicePort, string $serviceId, string $serviceSecret)
    {
        return new RemoteAuthProvider($authServiceHost, $authServicePort, $serviceId, $serviceSecret);
    }
}