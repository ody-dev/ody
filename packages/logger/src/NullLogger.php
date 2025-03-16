<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Null Logger
 * Logs messages nowhere (useful for testing or disabling logging)
 */
class NullLogger extends AbstractLogger
{
    /**
     * Create a null logger from configuration
     *
     * @param array $config
     * @return LoggerInterface
     */
    public static function create(array $config): LoggerInterface
    {
        return new self(
            $config['level'] ?? LogLevel::DEBUG,
            null // No formatter needed
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Do nothing
    }
}