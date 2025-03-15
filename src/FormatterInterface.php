<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger;

/**
 * Formatter Interface
 */
interface FormatterInterface
{
    /**
     * Format a log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    public function format(string $level, string $message, array $context = []): string;
}