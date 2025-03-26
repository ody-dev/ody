<?php

declare(strict_types=1);

namespace Ody\AMQP\ConnectionPool;

use Ody\ConnectionPool\Pool\PoolItemFactoryInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * @implements PoolItemFactoryInterface<AMQPStreamConnection>
 */
readonly class RabbitMQConnectionFactory implements PoolItemFactoryInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected string $host,
        protected int    $port,
        protected string $user,
        protected string $password,
        protected string $vhost = '/',
        protected ?array $options = null,
    )
    {
    }

    public function create(): mixed
    {
        return new AMQPStreamConnection(
            host: $this->host,
            port: $this->port,
            user: $this->user,
            password: $this->password,
            vhost: $this->vhost,
            insist: $this->options['insist'] ?? false,
            login_method: $this->options['login_method'] ?? 'AMQPLAIN',
            login_response: $this->options['login_response'] ?? null,
            locale: $this->options['locale'] ?? 'en_US',
            connection_timeout: $this->options['connection_timeout'] ?? 3.0,
            read_write_timeout: $this->options['read_write_timeout'] ?? 3.0,
            context: $this->options['context'] ?? null,
            keepalive: $this->options['keepalive'] ?? false,
            heartbeat: $this->options['heartbeat'] ?? 0,
        );
    }
}