<?php

namespace Ody\CQRS\Tests\Integration\Fixtures;

/**
 * Handler for user-related commands
 */
class UserCommandHandler
{
    /**
     * @param UserRepository $repository
     */
    public function __construct(
        private UserRepository $repository
    )
    {
    }

    /**
     * Handle the CreateUserCommand
     *
     * @param CreateUserCommand $command
     * @return void
     */
    public function handleCreateUser(CreateUserCommand $command): void
    {
        $user = new User(
            $command->getId(),
            $command->getName(),
            $command->getEmail()
        );

        $this->repository->save($user);
    }

    /**
     * Handle the UpdateUserCommand
     *
     * @param UpdateUserCommand $command
     * @return void
     */
    public function handleUpdateUser(UpdateUserCommand $command): void
    {
        $user = $this->repository->findById($command->getId());

        if ($user) {
            $user->setName($command->getName());
            $user->setEmail($command->getEmail());
            $this->repository->save($user);
        }
    }
}

/**
 * Handler for user-related queries
 */
class UserQueryHandler
{
    /**
     * @param UserRepository $repository
     */
    public function __construct(
        private UserRepository $repository
    )
    {
    }

    /**
     * Handle the GetUserByIdQuery
     *
     * @param GetUserByIdQuery $query
     * @return User|null
     */
    public function handleGetUserById(GetUserByIdQuery $query): ?User
    {
        return $this->repository->findById($query->getId());
    }

    /**
     * Handle the GetAllUsersQuery
     *
     * @param GetAllUsersQuery $query
     * @return array|User[]
     */
    public function handleGetAllUsers(GetAllUsersQuery $query): array
    {
        return $this->repository->findAll();
    }
}