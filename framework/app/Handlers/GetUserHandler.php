<?php

namespace App\Handlers;

use App\Repositories\UserRepository;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


class GetUserHandler implements RequestHandlerInterface
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->userRepository->findById($request->getAttribute('id'));

        if (empty($user)) {
            return new JsonResponse(['error' => 'Users not found'], 404);
        }

        return new JsonResponse(['data' => $user], 200);

    }
}