<?php

namespace Ody\CQRS\Discovery;

use Psr\Log\LoggerInterface;

class ClassNameResolver
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    /**
     * Resolve the fully qualified class name from a PHP file
     *
     * @param string $file
     * @return string|null
     */
    public function resolveFromFile(string $file): ?string
    {
        try {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->logger->warning("Could not read file: {$file}");
                return null;
            }

            $tokens = token_get_all($content);
            $namespace = '';
            $className = '';

            for ($i = 0; $i < count($tokens); $i++) {
                // Check for namespace
                if ($tokens[$i][0] === T_NAMESPACE) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_NAME_QUALIFIED) {
                            $namespace = $tokens[$j][1];
                            break;
                        }
                    }
                }

                // Check for class
                if ($tokens[$i][0] === T_CLASS) {
                    // Make sure this is not a class reference inside a method
                    if ($i >= 2 && $tokens[$i - 2][0] === T_NEW) {
                        continue;
                    }

                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $className = $tokens[$j][1];
                            break;
                        }
                    }
                    break;
                }
            }

            if ($namespace && $className) {
                return $namespace . '\\' . $className;
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error resolving class name from file {$file}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return null;
    }
}