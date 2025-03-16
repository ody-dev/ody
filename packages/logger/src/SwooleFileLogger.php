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
use Swoole\Coroutine;

/**
 * Swoole Coroutine-aware File Logger
 * Uses coroutines for non-blocking I/O when writing logs in Swoole environment
 */
class SwooleFileLogger extends FileLogger
{
    /**
     * {@inheritdoc}
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        // Check if we're running in a Swoole environment with coroutines enabled
        if (extension_loaded('swoole') && Coroutine::getCid() >= 0) {
            // Use Swoole's asynchronous file operations
            Coroutine::create(function () use ($level, $message) {
                if ($this->rotate && file_exists($this->filePath) && filesize($this->filePath) > $this->maxFileSize) {
                    $this->rotateLogFile();
                }

                $fp = Coroutine\System::fopen($this->filePath, 'a');
                if ($fp) {
                    Coroutine\System::fwrite($fp, $message . PHP_EOL);
                    Coroutine\System::fclose($fp);
                }
            });
        } else {
            // Fall back to standard file operations
            parent::write($level, $message, $context);
        }
    }
}