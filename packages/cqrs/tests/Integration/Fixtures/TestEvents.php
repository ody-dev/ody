<?php

namespace Ody\CQRS\Tests\Integration\Fixtures;

use Ody\CQRS\Interfaces\EventBusInterface;
use Ody\CQRS\Message\Command;
use Ody\CQRS\Message\Event;

/**
 * Event fired when a user is created
 */
class UserCreatedEvent extends Event
{
    /**
     * @param string $userId
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $userId,
        private string $name,
        private string $email
    )
    {
    }

    public function getUserId(): string
    {
        return $this->userId;
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
 * Event fired when a user is updated
 */
class UserUpdatedEvent extends Event
{
    /**
     * @param string $userId
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $userId,
        private string $name,
        private string $email
    )
    {
    }

    public function getUserId(): string
    {
        return $this->userId;
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
 * Test command that publishes events
 */
class TestCommand extends Command
{
    /**
     * @param string $commandId
     * @param string $userId
     * @param string $name
     * @param string $email
     */
    public function __construct(
        private string $commandId,
        private string $userId,
        private string $name,
        private string $email
    )
    {
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }

    public function getUserId(): string
    {
        return $this->userId;
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
 * Command handler that publishes events
 */
class CommandWithEvents
{
    /**
     * @param EventBusInterface $eventBus
     */
    public function __construct(
        private EventBusInterface $eventBus
    )
    {
    }

    /**
     * Handle the test command
     *
     * @param TestCommand $command
     * @return void
     */
    public function handle(TestCommand $command): void
    {
        // Publish a UserCreatedEvent
        $this->eventBus->publish(new UserCreatedEvent(
            $command->getUserId(),
            $command->getName(),
            $command->getEmail()
        ));

        // Also publish a UserUpdatedEvent
        $this->eventBus->publish(new UserUpdatedEvent(
            $command->getUserId(),
            $command->getName() . ' (Updated)',
            $command->getEmail()
        ));
    }
}

/**
 * Event handler for testing
 */
class TestEventHandler
{
    public bool $userCreatedHandled = false;
    public bool $userCreatedSecondaryHandled = false;
    public bool $userUpdatedHandled = false;

    public ?string $lastCreatedUserId = null;
    public ?string $lastCreatedUserName = null;
    public ?string $lastCreatedUserEmail = null;

    public ?string $lastUpdatedUserId = null;
    public ?string $lastUpdatedUserName = null;
    public ?string $lastUpdatedUserEmail = null;

    /**
     * Handle UserCreatedEvent
     *
     * @param UserCreatedEvent $event
     * @return void
     */
    public function handleUserCreated(UserCreatedEvent $event): void
    {
        $this->userCreatedHandled = true;
        $this->lastCreatedUserId = $event->getUserId();
        $this->lastCreatedUserName = $event->getName();
        $this->lastCreatedUserEmail = $event->getEmail();
    }

    /**
     * Secondary handler for UserCreatedEvent
     *
     * @param UserCreatedEvent $event
     * @return void
     */
    public function handleUserCreatedSecondary(UserCreatedEvent $event): void
    {
        $this->userCreatedSecondaryHandled = true;
    }

    /**
     * Handler that throws an exception
     *
     * @param UserCreatedEvent $event
     * @return void
     * @throws \RuntimeException
     */
    public function handleUserCreatedWithException(UserCreatedEvent $event): void
    {
        throw new \RuntimeException('Test exception in event handler');
    }

    /**
     * Handle UserUpdatedEvent
     *
     * @param UserUpdatedEvent $event
     * @return void
     */
    public function handleUserUpdated(UserUpdatedEvent $event): void
    {
        $this->userUpdatedHandled = true;
        $this->lastUpdatedUserId = $event->getUserId();
        $this->lastUpdatedUserName = $event->getName();
        $this->lastUpdatedUserEmail = $event->getEmail();
    }
}