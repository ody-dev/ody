<?php

namespace App\Handlers;

use App\Repositories\UserRepository;
use Ody\Foundation\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


class CreateUserHandler implements RequestHandlerInterface
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->userRepository->create($request->getParsedBody());

        return new JsonResponse(['data' => $user], 200);

    }
}