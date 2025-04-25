<?php

namespace Ody\Auth\Handlers;

use Ody\Auth\Authentication;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RefreshTokenHandler implements RequestHandlerInterface
{
    private $authService;

    public function __construct(Authentication $authService)
    {
        $this->authService = $authService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        if (!isset($data['refreshToken'])) {
            return new JsonResponse([
                'error' => 'Refresh token is required'
            ], 422);
        }

        $result = $this->authService->refreshToken($data['refreshToken']);

        if (!$result) {
            return new JsonResponse([
                'error' => 'Invalid refresh token'
            ], 401);
        }

        return new JsonResponse([
            'token' => $result['token'],
            'refreshToken' => $result['refreshToken'],
            'expiresIn' => $result['expiresIn'] ?? 3600
        ]);
    }
}