<?php

namespace App\Controllers;

use App\Commands\CreateUserCommand;
use App\Producers\UserCreatedProducer;
use App\Queries\GetUserById;
use App\Repositories\UserRepository;
use Ody\AMQP\AMQPClient;
use Ody\CQRS\Interfaces\CommandBusInterface;
use Ody\CQRS\Interfaces\QueryBusInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface   $queryBus,
        private readonly AMQPClient          $amqpClient,
        private UserRepository               $userRepository
    )
    {
    }

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        logger()->debug('UserController::createUser');
        $data = $request->getParsedBody();
        $this->commandBus->dispatch(
            new CreateUserCommand(
                name: $data['name'],
                email: $data['email'],
                password: $data['password']
            )
        );

        $this->amqpClient->publish(UserCreatedProducer::class, [
            1,
            $data['email'],
            $data['name']
        ]);

        return $response->json([
            'status' => 'success',
        ]);
    }

    public function getUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->queryBus->dispatch(
            new GetUserById(
                id: $args['id']
            )
        );

//        $users = DBAL::fetchAllAssociative(
//            'SELECT * FROM users WHERE id = ?',
//            [100]
//        );

        return $response->json([
            $this->userRepository->find(100)
//            $users
        ]);
    }
}