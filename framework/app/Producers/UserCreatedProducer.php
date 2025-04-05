<?php

namespace App\Producers;

use Ody\AMQP\Attributes\ProduceMessage;
use Ody\AMQP\Attributes\Producer;
use Ody\AMQP\Message\ProducerMessage;

#[Producer(exchange: 'user_events', routingKey: 'welcome_email', type: 'topic')]
final class UserCreatedProducer extends ProducerMessage
{
    #[ProduceMessage]
    public function __construct(int $userId, string $email, string $username)
    {
        $this->payload = [
            'id' => $userId,
            'email' => $email,
            'username' => $username,
            'created_at' => date('c'),
        ];
    }
}