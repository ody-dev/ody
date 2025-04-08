# Introduction

Command Query Responsibility Segregation is an architectural pattern that separates read operations (Queries) from write
operations (Commands). This separation allows for specialized optimization of each path, increased scalability, and
better maintainability of your codebase.

### Messages

At the heart of the CQRS system are three types of messages:

* **Commands**: Represent intentions to change state (e.g., `CreateUserCommand`, `UpdateProductCommand`)
* **Queries**: Represent requests for information without side effects (e.g., `GetUserByIdQuery`, `ListProductsQuery`)
* **Events**: Represent notifications that something has happened (e.g., `UserCreatedEvent`, `OrderShippedEvent`)

Messages in this implementation are simple PHP objects, intentionally free from framework-specific dependencies. This
design choice keeps your domain logic clean and portable.

### Handlers

For each message type, there are corresponding handlers:

* **Command Handlers**: Process commands and modify state
* **Query Handlers**: Process queries and return data
* **Event Handlers**: React to events (and multiple handlers can respond to a single event)

Handlers are services available in the dependency container. Using PHP 8 attributes, you can easily mark methods as
handlers:

```php
#[CommandHandler]
public function createUser(CreateUserCommand $command, EventBusInterface $eventBus)
{
    // Process command logic...
    $eventBus->publish(new UserCreatedEvent($userId));
}

#[QueryHandler]
public function getUserById(GetUserByIdQuery $query)
{
    // Retrieve and return data...
}

#[EventHandler]
public function notifyOnUserCreated(UserCreatedEvent $event)
{
    // React to event...
}
```

### Message Buses

Message buses serve as the transport mechanism that connects messages to their handlers:

* **Command Bus**: Routes commands to their respective command handlers
* **Query Bus**: Routes queries to their respective query handlers and returns results
* **Event Bus**: Distributes events to all registered event handlers

### How Does It All Fit Together?

When a command is dispatched through the Command Bus:

1. The bus identifies the appropriate handler based on the command's class
2. Middleware components may intercept the command for cross-cutting concerns
3. The handler processes the command, potentially emitting events
4. Events are published to the Event Bus, triggering any relevant event handlers
