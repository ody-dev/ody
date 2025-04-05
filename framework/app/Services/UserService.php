<?php

namespace App\Services;

use App\Commands\CreateUserCommand;
use App\Events\UserWasCreated;
use App\Models\User;
use App\Queries\GetUserById;
use Ody\CQRS\Attributes\Async;
use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Interfaces\EventBusInterface;

class UserService
{
    #[Async(channel: 'commands')]
    #[CommandHandler]
    public function createUser(CreateUserCommand $command, EventBusInterface $eventBus)
    {
        $user = User::create([
            'name' => $command->getName(),
            'email' => $command->getEmail(),
            'password' => $command->getPassword(),
        ]);

        $eventBus->publish(new UserWasCreated($user->id));
    }

    #[QueryHandler]
    public function getUserById(GetUserById $query)
    {
        return User::findOrFail($query->getId());
    }

    #[EventHandler]
    public function when(UserWasCreated $event): void
    {
        logger()->debug("Event: User was created: " . $event->getId());
    }
}