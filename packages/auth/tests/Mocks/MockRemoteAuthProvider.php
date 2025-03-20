<?php

namespace Ody\Auth\Tests\Mocks;

use Ody\Auth\AuthProviderInterface;

/**
 * Mock implementation of RemoteAuthProvider for testing
 * Avoids Swoole coroutine requirements
 */
class MockRemoteAuthProvider implements AuthProviderInterface
{
    protected $authServiceHost;
    protected $authServicePort;
    protected $serviceId;
    protected $serviceSecret;
    protected $serviceToken;
    protected $tokenExpiration;

    public function __construct(string $authServiceHost, int $authServicePort, string $serviceId, string $serviceSecret)
    {
        $this->authServiceHost = $authServiceHost;
        $this->authServicePort = $authServicePort;
        $this->serviceId = $serviceId;
        $this->serviceSecret = $serviceSecret;

        // In the mock, we'll just set a fake token immediately
        $this->serviceToken = 'mock_service_token_' . uniqid();
        $this->tokenExpiration = time() + 3600;
    }

    public function authenticate(string $username, string $password)
    {
        // Return mock user data
        return [
            'id' => 1,
            'email' => $username,
            'roles' => ['user'],
            'token' => 'user_token_' . uniqid(),
            'refreshToken' => 'refresh_token_' . uniqid()
        ];
    }

    public function validateToken(string $token)
    {
        // Return mock token data
        return [
            'valid' => true,
            'sub' => 1,
            'email' => 'test@example.com',
            'roles' => ['user']
        ];
    }

    public function refreshToken(string $refreshToken)
    {
        // Return mock refresh response
        return [
            'token' => 'new_token_' . uniqid(),
            'refreshToken' => 'new_refresh_token_' . uniqid(),
            'expiresIn' => 3600
        ];
    }

    public function getUser($id)
    {
        // Return mock user
        return [
            'id' => $id,
            'email' => "user{$id}@example.com",
            'roles' => ['user']
        ];
    }

    public function revokeToken(string $token)
    {
        // Return true to indicate success
        return true;
    }
}