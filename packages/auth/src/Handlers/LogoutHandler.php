<?php

namespace Ody\Auth\Handlers;

use Ody\Auth\Authentication;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LogoutHandler implements RequestHandlerInterface
{
    private $authService;

    public function __construct(Authentication $authService)
    {
        $this->authService = $authService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);

        $this->authService->revokeToken($token);

        return new JsonResponse([
            'message' => 'Logged out successfully'
        ]);
    }
}