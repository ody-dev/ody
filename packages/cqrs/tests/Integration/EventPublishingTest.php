<?php

namespace Ody\CQRS\Tests\Integration;

use Ody\Container\Container;
use Ody\CQRS\Bus\CommandBus;
use Ody\CQRS\Bus\EventBus;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Resolver\CommandHandlerResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration test focused on event publishing and handling
 */
class EventPublishingTest extends TestCase
{
    private $container;
    private $commandBus;
    private $eventBus;
    private $eventHandlerRegistry;
    private $commandHandlerRegistry;
    private $eventHandler;

    public function testDirectEventPublishing(): void
    {
        // Test direct event publishing
        $event = new \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent(
            'user123',
            'John Doe',
            'john@example.com'
        );

        $this->eventBus->publish($event);

        // Check that the event handler was called
        $this->assertTrue($this->eventHandler->userCreatedHandled);
        $this->assertEquals('user123', $this->eventHandler->lastCreatedUserId);
        $this->assertEquals('John Doe', $this->eventHandler->lastCreatedUserName);
    }

    public function testCommandThatPublishesEvents(): void
    {
        // Dispatch a command that publishes multiple events
        $command = new \Ody\CQRS\Tests\Integration\Fixtures\TestCommand(
            'command123',
            'user456',
            'Jane Doe',
            'jane@example.com'
        );

        $this->commandBus->dispatch($command);

        // Check that both events were handled
        $this->assertTrue($this->eventHandler->userCreatedHandled);
        $this->assertTrue($this->eventHandler->userUpdatedHandled);

        // Check created event data
        $this->assertEquals('user456', $this->eventHandler->lastCreatedUserId);
        $this->assertEquals('Jane Doe', $this->eventHandler->lastCreatedUserName);

        // Check updated event data
        $this->assertEquals('user456', $this->eventHandler->lastUpdatedUserId);
        $this->assertEquals('Jane Doe (Updated)', $this->eventHandler->lastUpdatedUserName);
    }

    public function testMultipleHandlersForSameEvent(): void
    {
        // Register a second handler for the same event
        $this->eventHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent::class,
            \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class,
            'handleUserCreatedSecondary'
        );

        $event = new \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent(
            'user789',
            'Alice Smith',
            'alice@example.com'
        );

        $this->eventBus->publish($event);

        // Check that both handlers were called
        $this->assertTrue($this->eventHandler->userCreatedHandled);
        $this->assertTrue($this->eventHandler->userCreatedSecondaryHandled);

        // Check that the data was correct
        $this->assertEquals('user789', $this->eventHandler->lastCreatedUserId);
        $this->assertEquals('Alice Smith', $this->eventHandler->lastCreatedUserName);
        $this->assertEquals('alice@example.com', $this->eventHandler->lastCreatedUserEmail);
    }

    public function testExceptionInEventHandlerDoesNotStopOtherHandlers(): void
    {
        // Register two handlers, one that throws an exception
        $this->eventHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent::class,
            \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class,
            'handleUserCreatedWithException' // This will throw
        );

        $this->eventHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent::class,
            \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class,
            'handleUserCreated' // This should still run
        );

        $event = new \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent(
            'userX',
            'Exception User',
            'exception@example.com'
        );

        // This should not throw
        $this->eventBus->publish($event);

        // The second handler should still have been called
        $this->assertTrue($this->eventHandler->userCreatedHandled);
        $this->assertEquals('userX', $this->eventHandler->lastCreatedUserId);
    }

    protected function setUp(): void
    {
        // Define test message classes
        require_once __DIR__ . '/Fixtures/TestEvents.php';

        // Create mock container
        $this->container = $this->createMock(Container::class);

        // Create the registries
        $this->eventHandlerRegistry = new EventHandlerRegistry();
        $this->commandHandlerRegistry = new CommandHandlerRegistry();

        // Create logger
        $logger = $this->createMock(LoggerInterface::class);

        // Create the event handler
        $this->eventHandler = new \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler();

        // Set up container to return our event handler
        $this->container->method('make')->willReturnCallback(function ($class) {
            if ($class === \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class) {
                return $this->eventHandler;
            }
            if ($class === \Ody\CQRS\Tests\Integration\Fixtures\CommandWithEvents::class) {
                return new \Ody\CQRS\Tests\Integration\Fixtures\CommandWithEvents($this->eventBus);
            }
            if ($class === LoggerInterface::class) {
                return $this->createMock(LoggerInterface::class);
            }
            if ($class === EventBus::class) {
                return $this->eventBus;
            }

            return null;
        });

        // Create the event bus
        $this->eventBus = new EventBus(
            $this->eventHandlerRegistry,
            $this->container,
            $logger,
            null
        );

        // Create command resolver that injects EventBus
        $commandHandlerResolver = new CommandHandlerResolver($this->container);

        // Create the command bus
        $this->commandBus = new CommandBus(
            $this->commandHandlerRegistry,
            $commandHandlerResolver
        );

        // Register event handlers
        $this->eventHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UserCreatedEvent::class,
            \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class,
            'handleUserCreated'
        );

        $this->eventHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\UserUpdatedEvent::class,
            \Ody\CQRS\Tests\Integration\Fixtures\TestEventHandler::class,
            'handleUserUpdated'
        );

        // Register command handlers
        $this->commandHandlerRegistry->registerHandler(
            \Ody\CQRS\Tests\Integration\Fixtures\TestCommand::class,
            \Ody\CQRS\Tests\Integration\Fixtures\CommandWithEvents::class,
            'handle'
        );
    }
}