<?php

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

/**
 * Manages Doctrine DBAL Connection instances on a per-coroutine basis,
 * mimicking the behavior of the original facade's static cache.
 */
class CoroutineConnectionResolver
{
    /**
     * Holds the connection instance for each active coroutine.
     * Key: "coroutine_id-connection_name"
     * Value: Doctrine\DBAL\Connection
     *
     * Needs to be static to persist across object instantiations within the worker,
     * but the Coroutine::defer ensures cleanup.
     * @var array<string, Connection>
     */
    protected static array $coroutineConnections = [];

    private $connectionFactory; // Can be ContainerInterface or the specific factory callable

    public function __construct(
        callable|ContainerInterface $connectionFactoryProvider,
        private LoggerInterface     $logger
    )
    {
        // Store how to get the factory
        $this->connectionFactory = $connectionFactoryProvider;
    }

    /**
     * Utility to clear the static cache (e.g., for testing or worker shutdown)
     */
    public static function clearCache(): void
    {
        self::$coroutineConnections = [];
        // Consider adding logging here if this method is used
    }

    /**
     * Gets the Doctrine DBAL Connection for the current coroutine.
     * Ensures only one instance is created per coroutine per connection name.
     *
     * @param string $name The connection name (e.g., 'default')
     * @return Connection
     */
    public function getConnection(string $name = 'local'): Connection
    {
        $cid = Coroutine::getCid();

        // Handle cases outside a coroutine (e.g., worker start, CLI)
        if ($cid < 0) {
            $this->logger->debug("CoroutineConnectionResolver: Non-coroutine context. Resolving '{$name}' directly via factory.");
            return $this->resolveConnection($name); // Resolve directly without caching
        }

        $connectionKey = $cid . '-' . $name;

        // Check if connection already exists for this coroutine and name
        if (!isset(self::$coroutineConnections[$connectionKey])) {
            $this->logger->debug("CoroutineConnectionResolver: Cache miss for key '{$connectionKey}'. Resolving '{$name}'.");

            // Resolve a new connection using the factory
            $connection = $this->resolveConnection($name);

            // Store it in the static cache for this coroutine
            self::$coroutineConnections[$connectionKey] = $connection;

            // IMPORTANT: Ensure cleanup when the coroutine finishes
            Coroutine::defer(function () use ($connectionKey) {
                if (isset(self::$coroutineConnections[$connectionKey])) {
                    $this->logger->debug("CoroutineConnectionResolver: Defer cleanup for connection key '{$connectionKey}'.");
                    // Optional: Add explicit transaction rollback check if necessary
                    // try {
                    //     if (self::$coroutineConnections[$connectionKey]->isTransactionActive()) {
                    //        self::$coroutineConnections[$connectionKey]->rollBack();
                    //     }
                    // } catch (\Throwable $e) { /* Log error */ }
                    unset(self::$coroutineConnections[$connectionKey]);
                    $this->logger->debug("CoroutineConnectionResolver: Cleaned up cache for key '{$connectionKey}'.");

                }
            });
            $this->logger->debug("CoroutineConnectionResolver: Cached connection for key '{$connectionKey}'.");

        } else {
            $this->logger->debug("CoroutineConnectionResolver: Cache hit for key '{$connectionKey}'. Returning cached connection.");
        }


        return self::$coroutineConnections[$connectionKey];
    }

    /**
     * Helper to resolve connection using the injected factory/container.
     */
    private function resolveConnection(string $name): Connection
    {
        if ($this->connectionFactory instanceof ContainerInterface) {
            /** @var callable $factory */
            $factory = $this->connectionFactory->get('dbal.connection.factory');
            return $factory($name);
        } elseif (is_callable($this->connectionFactory)) {
            // If the factory callable itself was injected
            $factory = $this->connectionFactory;
            return $factory($name);
        }
        throw new \LogicException("CoroutineConnectionResolver requires a valid ContainerInterface or callable factory.");
    }
}