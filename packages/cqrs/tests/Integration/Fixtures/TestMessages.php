<?php

namespace Ody\CQRS\Tests\Integration\Fixtures;

use Ody\CQRS\Message\Command;
use Ody\CQRS\Message\Query;

/**
 * Command to create a new user
 */
class CreateUserCommand extends Command
{
    /**
     * @param string $id
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $email
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

/**
 * Command to update an existing user
 */
class UpdateUserCommand extends Command
{
    /**
     * @param string $id
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $email
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

/**
 * Query to get a user by ID
 */
class GetUserByIdQuery extends Query
{
    /**
     * @param string $id
     */
    public function __construct(
        private string $id
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }
}

/**
 * Query to get all users
 */
class GetAllUsersQuery extends Query
{
}

/**
 * User model for the tests
 */
class User
{
    /**
     * @param string $id
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $email
    )
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}

/**
 * Simple in-memory user repository for tests
 */
class UserRepository
{
    /**
     * @var array|User[]
     */
    private array $users = [];

    /**
     * Save a user
     *
     * @param User $user
     * @return void
     */
    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    /**
     * Find a user by ID
     *
     * @param string $id
     * @return User|null
     */
    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    /**
     * Find all users
     *
     * @return array|User[]
     */
    public function findAll(): array
    {
        return array_values($this->users);
    }

    /**
     * Check if a user exists
     *
     * @param string $id
     * @return bool
     */
    public function exists(string $id): bool
    {
        return isset($this->users[$id]);
    }
}