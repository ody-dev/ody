<?php

declare(strict_types=1);

namespace Ody\AMQP\Message;

abstract class ProducerMessage
{
    /**
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * @var array<string, mixed>
     */
    protected array $properties = [
        'content_type' => 'text/plain',
        'delivery_mode' => 2, // persistent
    ];

    /**
     * Set delay in milliseconds (for delayed messages)
     */
    public function setDelayMs(int $milliseconds): void
    {
        $this->properties['application_headers'] = [
            'x-delay' => ['I', $milliseconds]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}