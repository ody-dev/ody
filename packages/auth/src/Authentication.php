<?php

namespace Ody\Auth;

use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Authentication implements AuthenticationInterface
{
    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $responseFactory;

    /**
     * Authentication constructor.
     *
     * @param AdapterInterface $adapter
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(
        AdapterInterface         $adapter,
        ResponseFactoryInterface $responseFactory
    )
    {
        $this->adapter = $adapter;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Authenticate a request
     *
     * @param ServerRequestInterface $request
     * @return IdentityInterface|null
     */
    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        return $this->adapter->authenticate($request);
    }

    /**
     * Create an unauthorized response
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized'
        ]));

        return $response;
    }

    /**
     * Authenticate user by credentials
     *
     * @param string $username
     * @param string $password
     * @return array|null User data with tokens or null if authentication fails
     */
    public function login(string $username, string $password): ?array
    {
        // This would typically call your user repository
        $userRepository = app(UserRepository::class);
        $user = $userRepository->findByEmail($username);

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        // Remove sensitive data
        unset($user['password']);

        // Generate tokens
        if (method_exists($this->adapter, 'generateToken')) {
            $token = $this->adapter->generateToken($user);
            $refreshToken = $this->adapter->generateRefreshToken($user['id']);

            return array_merge($user, [
                'token' => $token,
                'refreshToken' => $refreshToken,
                'expiresIn' => 3600 // Use your configured value
            ]);
        }

        return null;
    }

    /**
     * Logout a user by revoking their token
     *
     * @param string $token JWT token
     * @return bool Success status
     */
    public function logout(string $token): bool
    {
        return $this->adapter->revokeToken($token);
    }

    /**
     * Refresh an authentication token
     *
     * @param string $refreshToken
     * @return array|null New tokens or null if invalid
     */
    public function refreshToken(string $refreshToken): ?array
    {
        if (method_exists($this->adapter, 'validateRefreshToken')) {
            $payload = $this->adapter->validateRefreshToken($refreshToken);

            if (!$payload) {
                return null;
            }

            // Get user from the refresh token
            $userId = $payload['sub'] ?? null;
            if (!$userId) {
                return null;
            }

            $userRepository = app(UserRepository::class);
            $user = $userRepository->findById($userId);

            if (!$user) {
                return null;
            }

            // Generate new tokens
            $token = $this->adapter->generateToken($user);
            $newRefreshToken = $this->adapter->generateRefreshToken($userId);

            return [
                'token' => $token,
                'refreshToken' => $newRefreshToken,
                'expiresIn' => 3600
            ];
        }

        return null;
    }

    /**
     * Revoke a token
     *
     * @param string $token
     * @return bool Success status
     */
    public function revokeToken(string $token): bool
    {
        // Implement token revocation logic
        // This would typically add the token to a blacklist
        $tokenRepository = app('token.repository');
        return $tokenRepository->revokeToken($token);
    }

    /**
     * Get user by ID
     *
     * @param mixed $id
     * @return array|null User data or null if not found
     */
    public function getUser($id): ?array
    {
        $userRepository = app('user.repository');
        return $userRepository->findById($id);
    }
}