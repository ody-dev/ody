<?php

namespace Ody\Auth\Handlers;

use Ody\Auth\AuthManager;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        protected AuthManager $authManager
    )
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse([
                'error' => 'email and password are required'
            ], 422);
        }

        $result = $this->authManager->login($data['email'], $data['password']);

        if (!$result) {
            return new JsonResponse([
                'error' => 'Invalid credentials'
            ], 401);
        }

        return new JsonResponse([
            'message' => 'Login successful',
            'token' => $result['token'],
            'refreshToken' => $result['refreshToken'],
            'expiresIn' => $result['expiresIn'] ?? 3600,
            'user' => [
                'id' => $result['id'],
                'email' => $result['email'] ?? null,
            ]
        ]);
    }
}