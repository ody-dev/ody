<?php

namespace Ody\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;

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
    private static array $channels = [];

    /**
     * @var int Maximum channels per connection
     */
    private static int $maxChannelsPerConnection = 10;

    /**
     * Get a channel from the pool or create a new one
     *
     * @param string $connectionName Connection configuration name
     * @return AMQPChannel
     */
    public static function getChannel(string $connectionName = 'default'): AMQPChannel
    {
        // Get connection from the connection pool
        $connection = AMQPConnectionPool::getConnection($connectionName);

        // Initialize channel array for this connection if needed
        if (!isset(self::$channels[$connectionName])) {
            self::$channels[$connectionName] = [];
        }

        // Find an open channel or create a new one if under limit
        foreach (self::$channels[$connectionName] as $channel) {
            if ($channel->is_open()) {
                return $channel;
            }
        }

        // Remove closed channels
        self::$channels[$connectionName] = array_filter(
            self::$channels[$connectionName],
            function ($channel) {
                return $channel->is_open();
            }
        );

        // If we have room for more channels, create a new one
        if (count(self::$channels[$connectionName]) < self::$maxChannelsPerConnection) {
            $channel = $connection->channel();
            self::$channels[$connectionName][] = $channel;

            // Register channel with the connection pool for proper cleanup
            AMQPConnectionPool::registerChannel($connectionName, $channel);

            return $channel;
        }

        // If we're at the limit, reuse a random open channel
        $openChannels = array_values(array_filter(
            self::$channels[$connectionName],
            function ($channel) {
                return $channel->is_open();
            }
        ));

        if (empty($openChannels)) {
            // All channels are closed, create a new one
            $channel = $connection->channel();
            self::$channels[$connectionName][] = $channel;

            // Register channel with the connection pool
            AMQPConnectionPool::registerChannel($connectionName, $channel);

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
    public static function closeAll(): void
    {
        foreach (array_keys(self::$channels) as $connectionName) {
            self::closeChannels($connectionName);
        }
    }

    /**
     * Close all channels for a specific connection
     *
     * @param string $connectionName Connection configuration name
     * @return void
     */
    public static function closeChannels(string $connectionName): void
    {
        if (!isset(self::$channels[$connectionName])) {
            return;
        }

        foreach (self::$channels[$connectionName] as $channel) {
            try {
                if ($channel->is_open()) {
                    $channel->close();
                }
            } catch (\Throwable $e) {
                // Ignore errors during cleanup
                error_log("[AMQP] Error closing channel: " . $e->getMessage());
            }
        }

        self::$channels[$connectionName] = [];
    }

    /**
     * Set the maximum number of channels per connection
     *
     * @param int $max Maximum number of channels per connection
     * @return void
     */
    public static function setMaxChannelsPerConnection(int $max): void
    {
        self::$maxChannelsPerConnection = max(1, $max);
    }

    /**
     * Get pool statistics for monitoring/debugging
     *
     * @return array
     */
    public static function getStats(): array
    {
        $stats = [
            'total_channels' => 0,
            'connections' => [],
        ];

        foreach (self::$channels as $connectionName => $channels) {
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