<?php

namespace Ody\Support;

/**
 * Class AliasLoader
 *
 * Handles the loading of class aliases
 */
class AliasLoader
{
    /**
     * The array of class aliases.
     *
     * @var array
     */
    protected array $aliases;

    /**
     * Indicates if a loader has been registered.
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * The singleton instance of the loader.
     *
     * @var AliasLoader|null
     */
    protected static ?AliasLoader $instance = null;

    /**
     * Create a new AliasLoader instance.
     *
     * @param array $aliases
     */
    protected function __construct(array $aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Get or create the singleton alias loader instance.
     *
     * @param array $aliases
     * @return AliasLoader
     */
    public static function getInstance(array $aliases = []): AliasLoader
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($aliases);
        } else {
            static::$instance->merge($aliases);
        }

        return static::$instance;
    }

    /**
     * Merge the given aliases with the existing aliases.
     *
     * @param array $aliases
     * @return void
     */
    public function merge(array $aliases): void
    {
        $this->aliases = array_merge($this->aliases, $aliases);
    }

    /**
     * Load a class alias if it is registered.
     *
     * @param string $alias
     * @return bool|null
     */
    public function load(string $alias): ?bool
    {
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }

        return null;
    }

    /**
     * Add an alias to the loader.
     *
     * @param string $alias
     * @param string $class
     * @return void
     */
    public function alias(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /**
     * Register the loader on the auto-loader stack.
     *
     * @return void
     */
    public function register(): void
    {
        if (!$this->registered) {
            // Register with a higher priority than normal autoloaders
            spl_autoload_register([$this, 'load'], true, true);
            $this->registered = true;
        }
    }

    /**
     * Get the registered aliases.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Set the registered aliases.
     *
     * @param array $aliases
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Check if a loader has been registered.
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Set the "registered" state of the loader.
     *
     * @param bool $value
     * @return void
     */
    public function setRegistered(bool $value): void
    {
        $this->registered = $value;
    }
}