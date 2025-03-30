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
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Logger Interface with Swoole-ready methods
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Set log level for the logger
     *
     * @param string $level
     * @return self
     */
    public function setLevel(string $level): self;

    /**
     * Get current log level
     *
     * @return string
     */
    public function getLevel(): string;

    /**
     * Set formatter for the logger
     *
     * @param FormatterInterface $formatter
     * @return self
     */
    public function setFormatter(FormatterInterface $formatter): self;

    /**
     * Get current formatter
     *
     * @return FormatterInterface
     */
    public function getFormatter(): FormatterInterface;
}