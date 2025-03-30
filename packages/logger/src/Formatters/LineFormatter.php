<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Logger\Formatters;

/**
 * Line Formatter
 * Formats log messages as single lines with timestamp and level
 */
class LineFormatter implements FormatterInterface
{
    /**
     * @var string Line format
     */
    protected string $format = "[%datetime%] [%level%] %message% %context%";

    /**
     * @var string DateTime format
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param string|null $format
     * @param string|null $dateFormat
     */
    public function __construct(?string $format = null, ?string $dateFormat = null)
    {
        if ($format !== null) {
            $this->format = $format;
        }

        if ($dateFormat !== null) {
            $this->dateFormat = $dateFormat;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function format(string $level, string $message, array $context = []): string
    {
        $output = $this->format;

        // Replace placeholders
        $output = str_replace('%datetime%', date($this->dateFormat), $output);
        $output = str_replace('%level%', strtoupper($level), $output);
        $output = str_replace('%message%', $this->interpolateMessage($message, $context), $output);

        // Format context if not empty
        $contextStr = !empty($context) ? $this->formatContext($context) : '';
        $output = str_replace('%context%', $contextStr, $output);

        return $output;
    }

    /**
     * Interpolate message placeholders with context values
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolateMessage(string $message, array $context = []): string
    {
        // Replace {placeholders} with context values
        $replace = [];
        foreach ($context as $key => $val) {
            // Check if value can be converted to string
            if (is_string($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            } else if (is_scalar($val)) {
                // Handle scalar values (int, float, bool) by converting them to string
                $replace['{' . $key . '}'] = (string)$val;
            }
            // Non-scalar values (arrays, resources, etc.) are ignored for placeholder replacement
        }

        return strtr($message, $replace);
    }

    /**
     * Format context as string
     *
     * @param array $context
     * @return string
     */
    protected function formatContext(array $context): string
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = [
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => $value->getTraceAsString()
                ];
            }
        }

        return json_encode($context);
    }
}