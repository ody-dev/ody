<?php

namespace Ody\Support;

/**
 * Global Class Loader
 *
 * Custom autoloader that allows importing classes without their namespace.
 */
class GlobalClassLoader
{
    /**
     * Register the autoloader
     *
     * @return void
     */
    public static function register(): void
    {
        // Register our autoloader with a higher priority than Composer's
        spl_autoload_register([self::class, 'loadClass'], true, true);
    }

    /**
     * Load a class that might be a global import
     *
     * @param string $class Class name to load
     * @return bool True if the class was loaded
     */
    public static function loadClass(string $class): bool
    {
        // Skip classes with namespaces
        if (strpos($class, '\\') !== false) {
            return false;
        }

        // Check if this is a registered global import
        $fullClass = GlobalImports::resolve($class);

        if ($fullClass) {
            // If the class exists, alias it
            if (class_exists($fullClass, true)) {
                // Create an alias for the class if it's not already defined
                if (!class_exists($class, false)) {
                    class_alias($fullClass, $class);
                    return true;
                }
            }
        }

        return false;
    }
}