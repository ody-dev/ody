<?php
/*
 * This file is part of ODY framework.
 *
 * @link     https://ody.dev
 * @document https://ody.dev/docs
 * @license  https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Ody\Support;

/**
 * Class alias loader
 */
class AliasLoader
{
    /**
     * The array of class aliases.
     *
     * @var array
     */
    protected $aliases;

    /**
     * Indicates if a loader has been registered.
     *
     * @var bool
     */
    protected $registered = false;

    /**
     * The singleton instance of the loader.
     *
     * @var AliasLoader|null
     */
    protected static $instance = null;

    /**
     * Create a new AliasLoader instance.
     *
     * @param  array  $aliases
     */
    private function __construct(array $aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Get or create the singleton alias loader instance.
     *
     * @param  array  $aliases
     * @return AliasLoader
     */
    public static function getInstance(array $aliases = []): self
    {
        if (is_null(static::$instance)) {
            static::$instance = new static($aliases);
        }

        $aliases = array_merge(static::$instance->getAliases(), $aliases);

        static::$instance->setAliases($aliases);

        return static::$instance;
    }

    /**
     * Load a class alias if it is registered.
     *
     * @param  string  $alias
     * @return bool|null
     */
    public function load(string $alias)
    {
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
    }

    /**
     * Add an alias to the loader.
     *
     * @param  string  $alias
     * @param  string  $class
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
            $this->prependToLoaderStack();
            $this->registered = true;
        }
    }

    /**
     * Prepend the load method to the auto-loader stack.
     *
     * @return void
     */
    protected function prependToLoaderStack(): void
    {
        spl_autoload_register([$this, 'load'], true, true);
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
     * @param  array  $aliases
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Indicates if the loader has been registered.
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
     * @param  bool  $value
     * @return void
     */
    public function setRegistered(bool $value): void
    {
        $this->registered = $value;
    }

    /**
     * Set the real-time facade mode.
     *
     * @param  bool  $value
     * @return void
     */
    public static function setFacadeInstance(self $loader): void
    {
        static::$instance = $loader;
    }
}