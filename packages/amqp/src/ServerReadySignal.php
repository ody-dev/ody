<?php

namespace Ody\AMQP;

class ServerReadySignal
{
    private static bool $serverIsReady = false;
    private static array $readyFlags = [];

    /**
     * Mark the server as ready
     */
    public static function setReady(): void
    {
        self::$serverIsReady = true;

        // Also create a file flag that processes can check
        $flagFile = self::getServerReadyFlagPath();
        file_put_contents($flagFile, time());

        // Notify all registered processes
        self::notifyProcesses();
    }

    /**
     * Get the path to the server ready flag file
     */
    private static function getServerReadyFlagPath(): string
    {
        return '/tmp/ody_server_ready';
    }

    /**
     * Notify registered processes that the server is ready
     */
    private static function notifyProcesses(): void
    {
        foreach (array_keys(self::$readyFlags) as $pid) {
            if (posix_kill($pid, SIGUSR1)) {
                // Signal sent successfully
                unset(self::$readyFlags[$pid]);
            }
        }
    }

    /**
     * Check if the server is ready
     */
    public static function isReady(): bool
    {
        if (self::$serverIsReady) {
            return true;
        }

        // Also check for the flag file
        $flagFile = self::getServerReadyFlagPath();
        return file_exists($flagFile);
    }

    /**
     * Register a process to be notified when the server is ready
     */
    public static function registerProcess(int $pid): void
    {
        self::$readyFlags[$pid] = true;
    }
}