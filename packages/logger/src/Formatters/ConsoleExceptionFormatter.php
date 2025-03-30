<?php

namespace Ody\Logger\Formatters;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Throwable;

class ConsoleExceptionFormatter extends LineFormatter
{
    const SIMPLE_FORMAT = "[%datetime%] [%level_name%] %channel%: %message% %context% %extra%\n";

    public function format(LogRecord $record): string
    {
        // Check if there's an exception in the context
        $exception = null;
        if (isset($record->context['exception']) && $record->context['exception'] instanceof Throwable) {
            $exception = $record->context['exception'];
            // Remove exception from context to avoid double logging if using parent::format
            // unset($record->context['exception']);
        } elseif (isset($record->context['original_exception']) && $record->context['original_exception'] instanceof Throwable) {
            // Handle case from ErrorReporter logging
            $exception = $record->context['original_exception'];
        }

        $output = sprintf(
            "[%s] [%s] %s\n",
            $record->datetime->format('Y-m-d H:i:s'),
            $record->level->getName(),
            $record->message
        );


        // If an exception exists, format and append it nicely
        if ($exception) {
            $output .= sprintf(
                "  Exception: %s\n  Message: %s\n  In: %s:%d\n",
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
            $output .= "  Stack trace:\n";
            $trace = $exception->getTraceAsString();
            // Indent each line of the trace for readability
            foreach (explode("\n", $trace) as $line) {
                $output .= "    " . $line . "\n";
            }
        }

        // Append remaining context (optional, might be noisy)
        if (!empty($record->context) && !$exception) { // Avoid if exception already handled
            $output .= "  Context: " . $this->toJson($record->context, true) . "\n";
        }
        if (!empty($record->extra)) {
            $output .= "  Extra: " . $this->toJson($record->extra, true) . "\n";
        }


        return $output;
    }
}