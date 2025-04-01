<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;


use Ody\Logger\Formatters\FormatterInterface;
use Psr\Log\LogLevel;

/**
 * Extension of LogManager to support Swoole-specific loggers
 */
class SwooleLogManager extends LogManager
{
    /**
     * {@inheritdoc}
     */
    protected function createFileLogger(array $config, FormatterInterface $formatter): FileLogger
    {
        // Use Swoole-aware file logger if in a Swoole environment
        if (extension_loaded('swoole')) {
            // Add date suffix for daily files
            $path = $config['path'];
            if (strpos($path, 'daily-') !== false) {
                $path = str_replace('daily-', 'daily-' . date('Y-m-d') . '-', $path);
            }

            return new SwooleFileLogger(
                $path,
                $config['level'] ?? LogLevel::DEBUG,
                $formatter,
                $config['rotate'] ?? false,
                $config['max_file_size'] ?? 10485760
            );
        }

        // Otherwise use standard file logger
        return parent::createFileLogger($config, $formatter);
    }

    /**
     * Create a swoole table logger
     *
     * @param array $config
     * @param FormatterInterface $formatter
     * @return SwooleTableLogger
     */
    public function createSwooleTableLogger(array $config, FormatterInterface $formatter): SwooleTableLogger
    {
        return new SwooleTableLogger(
            $config['max_entries'] ?? 10000,
            $config['level'] ?? LogLevel::DEBUG,
            $formatter
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function createLogger(string $channel): SwooleTableLogger
    {
        $config = $this->config['channels'][$channel];

        if (!isset($config['driver'])) {
            throw new \InvalidArgumentException("Log channel '{$channel}' has no driver specified");
        }

        // Handle swoole-specific drivers
        if ($config['driver'] === 'swoole_table') {
            $formatter = $this->createFormatter($config);
            return $this->createSwooleTableLogger($config, $formatter);
        }

        // Use parent implementation for other drivers
        return parent::createLogger($channel);
    }
}