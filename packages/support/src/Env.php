<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Support;

use Dotenv\Dotenv;

/**
 * Environment configuration loader
 */
class Env
{
    /**
     * Application base path
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Whether environment has been loaded
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * Environment constructor
     *
     * @param string|null $basePath
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 3); // Default to project root
    }

    /**
     * Load environment variables from .env file
     *
     * @param string $environment Environment name (e.g., 'production', 'development')
     * @return void
     */
    public function load(string $environment = null): void
    {
        if ($this->loaded) {
            return;
        }

        // Create Dotenv instance
        $dotenv = Dotenv::createImmutable($this->basePath);

        // Load default .env file
        $this->loadFile($dotenv, '.env');

        // Load environment-specific .env file if provided
        if ($environment) {
            $this->loadFile($dotenv, ".env.{$environment}");
        }

        $this->loaded = true;
    }

    /**
     * Load a specific .env file if it exists
     *
     * @param Dotenv $dotenv
     * @param string $file
     * @return void
     */
    protected function loadFile(Dotenv $dotenv, string $file): void
    {
        if (file_exists($this->basePath . '/' . $file)) {
            $dotenv->load();
        }
    }

    /**
     * Get an environment variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // Convert specific string values
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }

    /**
     * Check if an environment variable exists
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }
}