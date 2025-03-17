<?php

namespace Ody\Auth;

use Ody\Config\Config;
use Ody\Container\Container;
use Ody\Support\Manager;

class AuthManager extends Manager
{
    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The registered custom driver extensions.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Create a new manager instance.
     *
     * @param  Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->config = config();
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function createDriver($driver)
    {
        // First, we will check if an extension has been registered for this driver
        if (isset($this->extensions[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create'.ucfirst($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new \InvalidArgumentException("Auth driver [{$driver}] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->extensions[$driver]($this->container, $driver, $this->getConfig($driver));
    }

    /**
     * Get the default authentication driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config->get('auth.defaults.guard', 'web');
    }

    /**
     * Set the default authentication driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->config->set('auth.defaults.guard', $name);
    }

    /**
     * Register a new callback based driver resolver.
     *
     * @param  string  $driver
     * @param  callable  $callback
     * @return $this
     */
    public function extend($driver, callable|\Closure $callback)
    {
        $this->extensions[$driver] = $callback;
        
        return $this;
    }

    /**
     * Get a guard instance by name.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function guard($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->driver($name);
    }

    /**
     * Create a session based authentication guard.
     *
     * @return SessionGuard
     */
    protected function createSessionDriver()
    {
        $provider = $this->getUserProvider(
            $this->getConfig('session.provider')
        );

        return new SessionGuard(
            'session',
            $provider,
            $this->container->make('session'),
            $this->container->make('request')
        );
    }

    /**
     * Create a token based authentication guard.
     *
     * @return TokenGuard
     */
    protected function createTokenDriver()
    {
        $provider = $this->getUserProvider(
            $this->getConfig('token.provider')
        );

        return new TokenGuard(
            $provider,
            $this->container->make('request'),
            $this->getConfig('token.input_key', 'api_token'),
            $this->getConfig('token.storage_key', 'api_token'),
            $this->getConfig('token.hash', false)
        );
    }

    /**
     * Get the user provider configuration.
     *
     * @param  string|null  $provider
     * @return array
     */
    protected function getUserProvider($provider = null)
    {
        $provider = $provider ?: $this->getDefaultUserProvider();

        return $this->container->make('auth.user.provider');
    }

    /**
     * Get the default user provider name.
     *
     * @return string
     */
    protected function getDefaultUserProvider()
    {
        return $this->config->get('auth.defaults.provider', 'users');
    }

    /**
     * Get the guard configuration.
     *
     * @param  string  $name
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function getConfig($name, $key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config->get("auth.guards.{$name}", $default);
        }

        return $this->config->get("auth.guards.{$name}.{$key}", $default);
    }
}
