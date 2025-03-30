<?php

namespace Ody\AMQP;

use Ody\Support\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;

/**
 * Channel pool for AMQP channels
 * This class manages a pool of reusable channels to reduce the overhead
 * of creating new channels for each message.
 */
class AMQPChannelPool
{
    /**
     * @var array<string, array<int, AMQPChannel>> Channel pool
     */
    private array $channels = [];

    /**
     * @var int Maximum channels per connection
     */
    private int $maxChannelsPerConnection = 10;

    /**
     * Constructor for the channel pool
     */
    public function __construct(
        private AMQPConnectionPool $connectionPool,
        Config                  $config,
        private LoggerInterface $logger
    )
    {
        // Load configuration
        $poolConfig = $config->get('amqp.pool', []);
        $this->maxChannelsPerConnection = $poolConfig['max_channels_per_connection'] ?? 10;
    }

    /**
     * Get a channel from the pool or create a new one
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPChannel
     */
    public function getChannel(string $connectionName = 'default'): AMQPChannel
    {
        // Get connection from the connection pool
        $connection = $this->connectionPool->getConnection($connectionName);

        // Initialize channel array for this connection if needed
        if (!isset($this->channels[$connectionName])) {
            $this->channels[$connectionName] = [];
        }

        // Find an open channel or create a new one if under limit
        foreach ($this->channels[$connectionName] as $channel) {
            if ($channel->is_open()) {
                return $channel;
            }
        }

        // Remove closed channels
        $this->channels[$connectionName] = array_filter(
            $this->channels[$connectionName],
            function ($channel) {
                return $channel->is_open();
            }
        );

        // If we have room for more channels, create a new one
        if (count($this->channels[$connectionName]) < $this->maxChannelsPerConnection) {
            $channel = $connection->channel();
            $this->channels[$connectionName][] = $channel;

            // Register channel with the connection pool for proper cleanup
            $this->connectionPool->registerChannel($connectionName, $channel);

            return $channel;
        }

        // If we're at the limit, reuse a random open channel
        $openChannels = array_values(array_filter(
            $this->channels[$connectionName],
            function ($channel) {
                return $channel->is_open();
            }
        ));

        if (empty($openChannels)) {
            // All channels are closed, create a new one
            $channel = $connection->channel();
            $this->channels[$connectionName][] = $channel;

            // Register channel with the connection pool
            $this->connectionPool->registerChannel($connectionName, $channel);

            return $channel;
        }

        // Return a random channel to distribute load
        return $openChannels[array_rand($openChannels)];
    }

    /**
     * Close all channels in the pool
     *
     * @return void
     */
    public function closeAll(): void
    {
        foreach (array_keys($this->channels) as $connectionName) {
            $this->closeChannels($connectionName);
        }
    }

    /**
     * Close all channels for a specific connection
     *
     * @param string $connectionName Connection configuration name
     * @return void
     */
    public function closeChannels(string $connectionName): void
    {
        if (!isset($this->channels[$connectionName])) {
            return;
        }

        foreach ($this->channels[$connectionName] as $channel) {
            try {
                if ($channel->is_open()) {
                    $channel->close();
                }
            } catch (\Throwable $e) {
                // Ignore errors during cleanup
                $this->logger->error("[AMQP] Error closing channel: " . $e->getMessage());
            }
        }

        $this->channels[$connectionName] = [];
    }

    /**
     * Set the maximum number of channels per connection
     *
     * @param int $max Maximum number of channels per connection
     * @return void
     */
    public function setMaxChannelsPerConnection(int $max): void
    {
        $this->maxChannelsPerConnection = max(1, $max);
    }

    /**
     * Get pool statistics for monitoring/debugging
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = [
            'total_channels' => 0,
            'connections' => [],
        ];

        foreach ($this->channels as $connectionName => $channels) {
            $openChannels = count(array_filter($channels, function ($channel) {
                return $channel->is_open();
            }));

            $stats['connections'][$connectionName] = [
                'total_channels' => count($channels),
                'open_channels' => $openChannels,
                'closed_channels' => count($channels) - $openChannels,
            ];

            $stats['total_channels'] += count($channels);
        }

        return $stats;
    }
}