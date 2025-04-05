<?php

namespace App\Consumers;

use Ody\AMQP\Attributes\ConsumeMessage;
use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Message\ConsumerMessage;
use Ody\AMQP\Message\Result;
use Ody\CQRS\Interfaces\CommandBusInterface;
use PhpAmqpLib\Message\AMQPMessage;

//#[Consumer(
//    exchange: 'user_events',
//    routingKey: 'user.created',
//    queue: 'welcome_email_sender',
//    prefetchCount: 10
//)]
#[Consumer(exchange: 'user_events', routingKey: 'welcome_email', queue: 'welcome_email_queue', type: 'topic')]
final class WelcomeEmailConsumer extends ConsumerMessage
{
    public function __construct(
        // Inject dependencies if needed
        // private EmailService $emailService,
        private readonly CommandBusInterface $commandBus,
    )
    {
    }

    #[ConsumeMessage]
    public function consumeMessage(array $data, AMQPMessage $message): Result
    {
        try {
            // Process the message
            // $this->emailService->sendWelcomeEmail($data['email']);

            // For testing:
            logger()->debug("⭐ CONSUMER ACTIVATED: UserWelcomeMail received message: " . json_encode($data));

            // Acknowledge the message
            return Result::ACK;
        } catch (\Exception $e) {
            // Log the error
            error_log('Failed to send welcome email: ' . $e->getMessage());

            // Retry the message later
            return Result::REQUEUE;
        }
    }
}