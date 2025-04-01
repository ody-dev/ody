<?php

namespace Ody\CQRS\Tests\Integration;

use Ody\Container\Container;
use Ody\CQRS\Bus\CommandBus;
use Ody\CQRS\Bus\EventBus;
use Ody\CQRS\Bus\QueryBus;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use Ody\CQRS\Handler\Resolver\QueryHandlerResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration test that tests a full command-query cycle
 */
class CommandQueryCycleTest extends TestCase
{
    private $container;
    private $commandBus;
    private $queryBus;
    private $eventBus;
    private $userRepository;

    public function testCreateAndQueryUser(): void
    {
        // Test creating a user with a command
        $createCommand = new \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand(
            'user123',
            'John Doe',
            'john@example.com'
        );

        $this->commandBus->dispatch($createCommand);

        // Verify the user was created
        $this->assertTrue($this->userRepository->exists('user123'));

        // Test querying the user
        $query = new \Ody\CQRS\Tests\Integration\Fixtures\GetUserByIdQuery('user123');
        $user = $this->queryBus->dispatch($query);

        $this->assertNotNull($user);
        $this->assertEquals('user123', $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
    }

    public function testUpdateAndQueryUser(): void
    {
        // First create a user
        $createCommand = new \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand(
            'user456',
            'Jane Doe',
            'jane@example.com'
        );

        $this->commandBus->dispatch($createCommand);

        // Update the user
        $updateCommand = new \Ody\CQRS\Tests\Integration\Fixtures\UpdateUserCommand(
            'user456',
            'Jane Smith',  // Changed name
            'jane@example.com'
        );

        $this->commandBus->dispatch($updateCommand);

        // Query the updated user
        $query = new \Ody\CQRS\Tests\Integration\Fixtures\GetUserByIdQuery('user456');
        $user = $this->queryBus->dispatch($query);

        $this->assertNotNull($user);
        $this->assertEquals('user456', $user->getId());
        $this->assertEquals('Jane Smith', $user->getName()); // Check name was updated
        $this->assertEquals('jane@example.com', $user->getEmail());
    }

    public function testQueryAllUsers(): void
    {
        // Create multiple users
        $this->commandBus->dispatch(new \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand(
            'user1',
            'User One',
            'user1@example.com'
        ));

        $this->commandBus->dispatch(new \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand(
            'user2',
            'User Two',
            'user2@example.com'
        ));

        $this->commandBus->dispatch(new \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand(
            'user3',
            'User Three',
            'user3@example.com'
        ));

        // Query all users
        $query = new \Ody\CQRS\Tests\Integration\Fixtures\GetAllUsersQuery();
        $users = $this->queryBus->dispatch($query);

        $this->assertCount(3, $users);

        // Check if users are returned in the expected order
        $this->assertEquals('user1', $users[0]->getId());
        $this->assertEquals('user2', $users[1]->getId());
        $this->assertEquals('user3', $users[2]->getId());
    }

    public function testQueryNonExistentUser(): void
    {
        $query = new \Ody\CQRS\Tests\Integration\Fixtures\GetUserByIdQuery('nonexistent');
        $user = $this->queryBus->dispatch($query);

        $this->assertNull($user);
    }

    protected function setUp(): void
    {
        // Define test message classes
        require_once __DIR__ . '/Fixtures/TestMessages.php';

        // Define test handler classes
        require_once __DIR__ . '/Fixtures/TestHandlers.php';

        // Define the test repository
        $this->userRepository = new \Ody\CQRS\Tests\Integration\Fixtures\UserRepository();

        // Set up the container
        $this->container = $this->createMock(Container::class);
        $this->container->method('make')->willReturnCallback(function ($class) {
            if ($class === \Ody\CQRS\Tests\Integration\Fixtures\UserCommandHandler::class) {
                return new \Ody\CQRS\Tests\Integration\Fixtures\UserCommandHandler($this->userRepository);
            }
            if ($class === \Ody\CQRS\Tests\Integration\Fixtures\UserQueryHandler::class) {
                return new \Ody\CQRS\Tests\Integration\Fixtures\UserQueryHandler($this->userRepository);
            }
            if ($class === LoggerInterface::class) {
                return $this->createMock(LoggerInterface::class);
            }
            if ($class === EventBus::class) {
                return $this->eventBus;
            }

            return null;
        });

        // Set up the registries
        $commandRegistry = new CommandHandlerRegistry();
        $queryRegistry = new QueryHandlerRegistry();
        $eventRegistry = new EventHandlerRegistry();

        // Register handlers
        $commandRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\CreateUserCommand::class,
            \Ody\CQRS\Tests\Integration\Fixtures\UserCommandHandler::class,
            'handleCreateUser'
        );

        $commandRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UpdateUserCommand::class,
            \Ody\CQRS\Tests\Integration\Fixtures\UserCommandHandler::class,
            'handleUpdateUser'
        );

        $queryRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\GetUserByIdQuery::class,
            \Ody\CQRS\Tests\Integration\Fixtures\UserQueryHandler::class,
            'handleGetUserById'
        );

        $queryRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\GetAllUsersQuery::class,
            \Ody\CQRS\Tests\Integration\Fixtures\UserQueryHandler::class,
            'handleGetAllUsers'
        );

        // Set up the handler resolvers
        $commandHandlerResolver = new CommandHandlerResolver($this->container);
        $queryHandlerResolver = new QueryHandlerResolver($this->container);

        // Create logger
        $logger = $this->createMock(LoggerInterface::class);

        // Set up the buses
        $this->eventBus = new EventBus($eventRegistry, $this->container, $logger, null);
        $this->commandBus = new CommandBus($commandRegistry, $commandHandlerResolver);
        $this->queryBus = new QueryBus($queryRegistry, $queryHandlerResolver);
    }
}