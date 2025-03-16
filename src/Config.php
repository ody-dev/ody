<?php
namespace Ody\Support;

/**
 * Configuration repository
 */
class Config
{
    /**
     * All of the configuration items.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Track which files have been loaded to prevent recursion
     *
     * @var array
     */
    protected array $loadedFiles = [];

    /**
     * Create a new configuration repository.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (is_null($key)) {
            return $this->items;
        }

        if (isset($this->items[$key])) {
            return $this->processValue($this->items[$key]);
        }

        $segments = explode('.', $key);
        $items = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($items) || !array_key_exists($segment, $items)) {
                return $this->processValue($default);
            }

            $items = $items[$segment];
        }

        return $this->processValue($items);
    }

    /**
     * Process configuration value, resolving env variables if needed
     *
     * @param mixed $value
     * @return mixed
     */
    protected function processValue($value)
    {
        // If value is an array, process each item recursively
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->processValue($item);
            }
            return $value;
        }

        // Process string values for environment variable placeholders
        if (is_string($value) && strpos($value, 'env(') === 0) {
            // Extract env key and default value
            $matches = [];
            if (preg_match('/env\(([^,]+)(?:,\s*([^)]+))?\)/', $value, $matches)) {
                $envKey = trim($matches[1], '\'"`');
                $envDefault = isset($matches[2]) ? trim($matches[2], '\'"`') : null;
                return env($envKey, $envDefault);
            }
        }

        return $value;
    }

    /**
     * Set a given configuration value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $items = &$this->items;

        while (count($keys) > 1) {
            $key = array_shift($keys);
            if (!isset($items[$key]) || !is_array($items[$key])) {
                $items[$key] = [];
            }
            $items = &$items[$key];
        }

        $items[array_shift($keys)] = $value;
    }

    /**
     * Load configuration files from a directory with safeguards against recursion.
     *
     * @param string $path
     * @return void
     */
    public function loadFromDirectory(string $path): void
    {
        // Early return if not a directory or it's not readable
        if (!is_dir($path) || !is_readable($path)) {
            return;
        }

        // Get PHP files in the directory
        $files = [];

        // First, collect all files without actually loading them
        $dirContents = scandir($path);
        if ($dirContents === false) {
            return;
        }

        foreach ($dirContents as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . '/' . $file;

            // Only consider PHP files
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $files[] = [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'path' => $filePath
                ];
            }
        }

        // Now load each file individually with safety checks
        foreach ($files as $file) {
            $this->loadFile($file['name'], $file['path']);
        }
    }

    /**
     * Load a single configuration file.
     *
     * @param string $name Configuration name (filename without extension)
     * @param string $path Full path to the file
     * @return void
     */
    public function loadFile(string $name, string $path): void
    {
        // Skip if already loaded to prevent recursion
        if (isset($this->loadedFiles[$path])) {
            return;
        }

        // Mark as loaded before requiring to prevent recursion
        $this->loadedFiles[$path] = true;

        try {
            // Load the file which should return an array
            $config = require $path;

            // Only store if it's an array
            if (is_array($config)) {
                $this->items[$name] = $config;
            }
        } catch (\Throwable $e) {
            // Log error but continue processing other files
            error_log("Error loading config file {$path}: " . $e->getMessage());
        }
    }

    /**
     * Get all of the configuration items.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Merge configuration items with the existing ones.
     *
     * @param array $items
     * @return void
     */
    public function merge(array $items): void
    {
        $this->items = array_merge_recursive($this->items, $items);
    }

    /**
     * Merge configuration items for a specific key
     *
     * @param string $key
     * @param array $items
     * @return void
     */
    public function mergeKey(string $key, array $items): void
    {
        $current = $this->get($key, []);
        if (is_array($current)) {
            $this->set($key, array_merge_recursive($current, $items));
        } else {
            $this->set($key, $items);
        }
    }
}