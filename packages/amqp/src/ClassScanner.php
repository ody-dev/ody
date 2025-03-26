<?php

declare(strict_types=1);

namespace Ody\AMQP;

use Ody\AMQP\Attributes\Consumer;
use Ody\AMQP\Attributes\Producer;
use Ody\AMQP\Message\ConsumerMessage;
use Ody\AMQP\Message\ProducerMessage;
use ReflectionClass;

class ClassScanner
{
    /**
     * Find all classes in the given paths with the Consumer attribute
     *
     * @param array $paths Directory paths to scan
     * @return array Array of fully qualified class names
     */
    public static function findConsumerClasses(array $paths): array
    {
        $classes = self::scanPaths($paths);
        $consumerClasses = [];

        foreach ($classes as $class) {
            try {
                $reflection = new ReflectionClass($class);

                // Skip if abstract or interface
                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }

                // Skip if not a consumer
                if (!$reflection->isSubclassOf(ConsumerMessage::class)) {
                    continue;
                }

                // Skip if doesn't have the Consumer attribute
                $attributes = $reflection->getAttributes(Consumer::class);
                if (empty($attributes)) {
                    continue;
                }

                $consumerClasses[] = $class;
            } catch (\Throwable $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }

        return $consumerClasses;
    }

    /**
     * Scan paths for PHP classes
     *
     * @param array $paths
     * @return array
     */
    private static function scanPaths(array $paths): array
    {
        $classes = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                // Skip non-PHP files
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                // Get the file contents
                $content = file_get_contents($file->getRealPath());

                // Extract namespace
                preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
                $namespace = $namespaceMatches[1] ?? '';

                // Extract class name
                preg_match('/class\s+([^\s{]+)/', $content, $classMatches);
                if (empty($classMatches[1])) {
                    continue;
                }

                $className = $classMatches[1];
                $fullyQualifiedName = $namespace . '\\' . $className;

                // Add to classes list
                $classes[] = $fullyQualifiedName;
            }
        }

        return $classes;
    }

    /**
     * Find all classes in the given paths with the Producer attribute
     *
     * @param array $paths Directory paths to scan
     * @return array Array of fully qualified class names
     */
    public static function findProducerClasses(array $paths): array
    {
        $classes = self::scanPaths($paths);
        $producerClasses = [];

        foreach ($classes as $class) {
            try {
                $reflection = new ReflectionClass($class);

                // Skip if abstract or interface
                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }

                // Skip if not a producer
                if (!$reflection->isSubclassOf(ProducerMessage::class)) {
                    continue;
                }

                // Skip if doesn't have the Producer attribute
                $attributes = $reflection->getAttributes(Producer::class);
                if (empty($attributes)) {
                    continue;
                }

                $producerClasses[] = $class;
            } catch (\Throwable $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }

        return $producerClasses;
    }
}