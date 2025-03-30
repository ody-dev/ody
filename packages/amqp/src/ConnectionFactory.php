<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Exception;
use Ody\Support\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Factory class for creating AMQP connections
 */
readonly class ConnectionFactory
{
    /**
     * Constructor
     */
    public function __construct(
        private Config $config
    )
    {
    }

    /**
     * Create a new AMQP connection
     *
     * @param string $connectionName Name of the connection configuration to use
     * @return AMQPStreamConnection
     * @throws Exception
     */
    public function createConnection(string $connectionName = 'default'): AMQPStreamConnection
    {
        $connectionConfig = $this->config->get("amqp.{$connectionName}", $this->config->get('amqp.default', []));

        return new AMQPStreamConnection(
            host: $connectionConfig['host'] ?? 'localhost',
            port: $connectionConfig['port'] ?? 5672,
            user: $connectionConfig['user'] ?? 'guest',
            password: $connectionConfig['password'] ?? 'guest',
            vhost: $connectionConfig['vhost'] ?? '/',
            insist: ($connectionConfig['params']['insist'] ?? false),
            login_method: ($connectionConfig['params']['login_method'] ?? 'AMQPLAIN'),
            login_response: null,
            locale: ($connectionConfig['params']['locale'] ?? 'en_US'),
            connection_timeout: ($connectionConfig['params']['connection_timeout'] ?? 3.0),
            read_write_timeout: ($connectionConfig['params']['read_write_timeout'] ?? 3.0),
            context: null,
            keepalive: ($connectionConfig['params']['keepalive'] ?? true),
            heartbeat: ($connectionConfig['params']['heartbeat'] ?? 60),
        );
    }
}