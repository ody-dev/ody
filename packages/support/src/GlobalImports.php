<?php

namespace Ody\Support;

/**
 * Global Imports Manager
 *
 * Handles the registration of global class imports that allow using classes
 * without their full namespace.
 */
class GlobalImports
{
    /**
     * @var array Map of class basename to full class name
     */
    protected static array $imports = [];

    /**
     * Register multiple global imports
     *
     * @param array $classes Array of full class names
     * @return void
     */
    public static function registerMany(array $classes): void
    {
        foreach ($classes as $class) {
            self::register($class);
        }
    }

    /**
     * Register a global import
     *
     * @param string $class Full class name
     * @return void
     */
    public static function register(string $class): void
    {
        $basename = basename(str_replace('\\', '/', $class));
        self::$imports[$basename] = $class;
    }

    /**
     * Get the full class name for a short name
     *
     * @param string $basename Short class name
     * @return string|null Full class name or null if not found
     */
    public static function resolve(string $basename): ?string
    {
        return self::$imports[$basename] ?? null;
    }

    /**
     * Get all registered imports
     *
     * @return array Map of class basenames to full class names
     */
    public static function all(): array
    {
        return self::$imports;
    }
}