# RabbitMQ Integration for ODY Framework

This package provides RabbitMQ integration for the ODY framework, allowing you to easily implement asynchronous
messaging patterns in your application.

## Installation

```bash
composer require ody/amqp
```

## Configuration

First, publish the configuration file:

```bash
php artisan publish:config ody/amqp
```

This will create a `config/amqp.php` file where you can configure your RabbitMQ connections:

```php
return [
    'enable' => true,
    
    'default' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        
        'concurrent' => [
            'limit' => 10,  // Max concurrent consumers per process
        ],
        
        'pool' => [
            'connections' => 5,  // Number of connections per worker
        ],
        
        'params' => [
            'connection_timeout' => 3.0,
            'read_write_timeout' => 3.0,
            'heartbeat' => 60,
            'keepalive' => true,
        ],
    ],
    
    // You can define multiple connection pools
    'analytics' => [
        'host' => 'analytics-rabbitmq',
        // Other connection settings
    ],
    
    'producer' => [
        'paths' => ['app/Producers'],
        'retry' => [
            'max_attempts' => 3,
            'initial_interval' => 1000,  // ms
            'multiplier' => 2.0,
            'max_interval' => 10000,  // ms
        ],
    ],
    
    'consumer' => [
        'paths' => ['app/Consumers'],
        'prefetch_count' => 10,
        'auto_declare' => true,
    ],
    
    'process' => [
        'enable' => true,
        'max_consumers' => 10,
        'auto_restart' => true,
    ]
];
```

## Setting Up Docker

For local development, you can use Docker to run RabbitMQ:

```bash
docker run -d --name rabbitmq \
  -p 5672:5672 \
  -p 15672:15672 \
  -e RABBITMQ_DEFAULT_USER=admin \
  -e RABBITMQ_DEFAULT_PASS=password \
  rabbitmq:3-management
```

Or use docker-compose:

```yaml
version: '3.8'

services:
  rabbitmq:
    image: rabbitmq:3-management
    container_name: rabbitmq
    ports:
      - "5672:5672"   # AMQP protocol port
      - "15672:15672" # Management UI port
    environment:
      - RABBITMQ_DEFAULT_USER=admin
      - RABBITMQ_DEFAULT_PASS=password
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
      - rabbitmq_log:/var/log/rabbitmq

volumes:
  rabbitmq_data:
  rabbitmq_log:
```

## Creating Producers

Producers send messages to RabbitMQ. Create a producer class in your `app/Producers` directory:

```php
<?php

namespace App\Producers;

use Ody\AMQP\Attributes\Producer;
use Ody\AMQP\Message\ProducerMessage;

#[Producer(exchange: 'user_events', routingKey: 'user.created', type: 'topic')]
class UserCreatedProducer extends ProducerMessage
{
    public function __construct(
        private int $userId,
        private string $email,
        private string $username
    ) {
        $this->payload = [
            'user_id' => $this->userId,
            'email' => $this->email,
            'username' => $this->username,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}
```

## Creating Consumers

Consumers receive and process messages from RabbitMQ. Create a consumer class in your `app/Consumers` directory:

```php
<?php

namespace App\Consumers;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\ConsumerMessage;
use Ody\AMQP\Message\Result;
use PhpAmqpLib\Message\AMQPMessage;

#[Consumer(
    exchange: 'user_events',
    routingKey: 'user.created',
    queue: 'welcome_email_queue',
    type: 'topic'
)]
class WelcomeEmailConsumer extends ConsumerMessage
{
    public function consumeMessage(array $data, AMQPMessage $message): Result
    {
        try {
            // Process the message
            $userId = $data['user_id'];
            $email = $data['email'];
            $username = $data['username'];
            
            // Send welcome email
            // $this->emailService->sendWelcomeEmail($email, $username);
            
            // Log success
            error_log("Welcome email sent to $email for user $userId");
            
            // Acknowledge the message
            return Result::ACK;
        } catch (\Exception $e) {
            // Log the error
            error_log("Failed to send welcome email: " . $e->getMessage());
            
            // Reject and requeue the message for retry
            return Result::REQUEUE;
        }
    }
}
```

## Publishing Messages

To publish messages in your application code:

```php
use Ody\AMQP\AMQP;
use App\Producers\UserCreatedProducer;

// In your controller or service
public function registerUser(array $userData)
{
    // Create user in database
    $user = $this->userRepository->create($userData);
    
    // Publish event
    AMQP::publish(UserCreatedProducer::class, [
        $user->id,              // userId
        $user->email,           // email
        $user->username         // username
    ]);
    
    return $user;
}
```

## Delayed Messages

You can publish messages with a delay:

```php
// Send a reminder after 24 hours
AMQP::publishDelayed(ReminderProducer::class, [
    $user->id,
    'Your trial is about to expire'
], 86400000); // 24 hours in milliseconds
```

## Working with Topic Exchanges

Topic exchanges provide flexible routing:

```php
// Producer
#[Producer(exchange: 'notifications', routingKey: 'user.123.email', type: 'topic')]
class UserNotificationProducer extends ProducerMessage { /* ... */ }

// Consumer with wildcards
#[Consumer(
    exchange: 'notifications',
    routingKey: 'user.*.email',  // Match any user ID
    queue: 'email_notifications',
    type: 'topic'
)]
class EmailNotificationConsumer extends ConsumerMessage { /* ... */ }
```

## Message Results

Consumers should return one of these results:

- `Result::ACK`: Acknowledge the message (successfully processed)
- `Result::NACK`: Negative acknowledgment (failed to process, don't requeue)
- `Result::REQUEUE`: Reject and requeue the message (retry later)
- `Result::DROP`: Reject the message and drop it (don't retry)

```php
public function consumeMessage(array $data, AMQPMessage $message): Result
{
    try {
        // Process message
        return Result::ACK;
    } catch (\Exception $e) {
        // Decide based on error type
        if ($e instanceof TemporaryFailureException) {
            return Result::REQUEUE;  // Retry later
        }
        
        // Permanent failure
        return Result::DROP;  // Don't retry
    }
}
```

## Monitoring and Management

RabbitMQ provides a management UI available at `http://localhost:15672/` (default username/password is the one you
configured).

You can use this interface to:

- Monitor queues and exchanges
- View message rates and consumer activity
- Manage exchanges, queues, and bindings
- View connections and channels

## Troubleshooting

### Common Issues

1. **Connection Refused**: Check your RabbitMQ server is running and the connection details are correct.
2. **Exchange/Queue Not Found**: Verify exchange and queue names match between producers and consumers.
3. **Type Mismatch**: Ensure exchange types match between producers and consumers (e.g., 'topic', 'direct', 'fanout').
4. **Messages Not Being Consumed**: Check that consumers are running and bound to the correct exchange and routing key.

### Debugging

Enable detailed logging in your consumers:

```php
public function consumeMessage(array $data, AMQPMessage $message): Result
{
    error_log("Received message: " . json_encode($data));
    error_log("Delivery tag: " . $message->getDeliveryTag());
    // Rest of your processing
}
```

## Best Practices

1. **Use Topic Exchanges**: They provide more flexibility than direct exchanges.
2. **Implement Error Handling**: Always handle exceptions in consumers and return appropriate result codes.
3. **Consider Message Idempotency**: Design your consumers to handle duplicate messages safely.
4. **Set Appropriate Prefetch Count**: Adjust based on your processing speed and resource constraints.
5. **Monitor Queue Lengths**: Long queues may indicate processing bottlenecks.
6. **Use Descriptive Routing Keys**: Follow a convention like `entity.action.type` for better organization.

## Next Steps

- Implement dead letter exchanges for failed messages
- Set up message TTL (time-to-live) for expiring old messages
- Implement circuit breakers for external service calls
- Add message batching for better performance
- Set up message priorities for critical messages

---

For more advanced usage and configuration options, refer to the full documentation or explore
the [RabbitMQ official documentation](https://www.rabbitmq.com/documentation.html).