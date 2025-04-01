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
 * Swoole Table Logger
 * Stores logs in Swoole Table for high-performance in-memory logging
 */
class SwooleTableLogger extends AbstractLogger
{
    /**
     * @var \Swoole\Table Swoole table for storing logs
     */
    protected $table;

    /**
     * @var int Maximum number of log entries to keep
     */
    protected int $maxEntries;

    /**
     * @var int Current log index
     */
    protected int $currentIndex = 0;

    /**
     * Constructor
     *
     * @param int $maxEntries Maximum number of log entries to keep
     * @param string $level
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        int $maxEntries = 10000,
        string $level = LogLevel::DEBUG,
        ?FormatterInterface $formatter = null
    ) {
        parent::__construct($level, $formatter);

        $this->maxEntries = $maxEntries;
        $this->initializeTable();
    }

    /**
     * Initialize Swoole table
     *
     * @return void
     */
    protected function initializeTable(): void
    {
        // Check if Swoole extension is loaded
        if (!extension_loaded('swoole')) {
            throw new \RuntimeException('Swoole extension is required for SwooleTableLogger');
        }

        // Create a table with columns for log data
        $this->table = new \Swoole\Table($this->maxEntries);
        $this->table->column('timestamp', \Swoole\Table::TYPE_STRING, 20);
        $this->table->column('level', \Swoole\Table::TYPE_STRING, 10);
        $this->table->column('message', \Swoole\Table::TYPE_STRING, 8192);
        $this->table->column('context', \Swoole\Table::TYPE_STRING, 8192);
        $this->table->create();
    }

    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Get the next index (with wraparound)
        $index = $this->currentIndex % $this->maxEntries;
        $this->currentIndex++;

        // Store the log entry
        $this->table->set((string)$index, [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => !empty($context) ? json_encode($context) : ''
        ]);
    }

    /**
     * Get all log entries
     *
     * @return array
     */
    public function getAll(): array
    {
        $logs = [];
        foreach ($this->table as $index => $row) {
            $logs[] = [
                'timestamp' => $row['timestamp'],
                'level' => $row['level'],
                'message' => $row['message'],
                'context' => !empty($row['context']) ? json_decode($row['context'], true) : []
            ];
        }

        return $logs;
    }

    /**
     * Flush logs to a destination
     *
     * @param LoggerInterface $destination Logger to flush logs to
     * @param bool $clear Whether to clear the table after flushing
     * @return void
     */
    public function flush(LoggerInterface $destination, bool $clear = false): void
    {
        foreach ($this->getAll() as $log) {
            $level = strtolower($log['level']);
            $destination->log($level, $log['message'], $log['context']);
        }

        if ($clear) {
            $this->clear();
        }
    }

    /**
     * Clear all log entries
     *
     * @return void
     */
    public function clear(): void
    {
        foreach ($this->table as $index => $row) {
            $this->table->del($index);
        }

        $this->currentIndex = 0;
    }
}