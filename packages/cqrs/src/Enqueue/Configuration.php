<?php

namespace Ody\CQRS\Enqueue;

class Configuration
{
    /**
     * @var bool
     */
    protected bool $asyncEnabled = true;

    /**
     * @var array
     */
    protected array $asyncCommands = [];

    /**
     * @var string
     */
    protected string $defaultCommandTopic = 'commands';

    /**
     * @var string
     */
    protected string $defaultEventTopic = 'events';

    /**
     * @var array
     */
    protected array $commandTopics = [];

    /**
     * @var array
     */
    protected array $eventTopics = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (isset($config['async_enabled'])) {
            $this->asyncEnabled = (bool)$config['async_enabled'];
        }

        if (isset($config['async_commands']) && is_array($config['async_commands'])) {
            $this->asyncCommands = $config['async_commands'];
        }

        if (isset($config['default_command_topic'])) {
            $this->defaultCommandTopic = $config['default_command_topic'];
        }

        if (isset($config['default_event_topic'])) {
            $this->defaultEventTopic = $config['default_event_topic'];
        }

        if (isset($config['command_topics']) && is_array($config['command_topics'])) {
            $this->commandTopics = $config['command_topics'];
        }

        if (isset($config['event_topics']) && is_array($config['event_topics'])) {
            $this->eventTopics = $config['event_topics'];
        }
    }

    /**
     * Checks if async processing is enabled
     *
     * @return bool
     */
    public function isAsyncEnabled(): bool
    {
        return $this->asyncEnabled;
    }

    /**
     * Determines if a command should be run asynchronously
     *
     * @param string $commandClass
     * @return bool
     */
    public function shouldCommandRunAsync(string $commandClass): bool
    {
        // By default, all commands are async if async is enabled
        // unless specified in the asyncCommands array
        if (empty($this->asyncCommands)) {
            return true;
        }

        return in_array($commandClass, $this->asyncCommands);
    }

    /**
     * Gets the topic name for a command
     *
     * @param string $commandClass
     * @return string
     */
    public function getCommandTopic(string $commandClass): string
    {
        return $this->commandTopics[$commandClass] ?? $this->defaultCommandTopic;
    }

    /**
     * Gets the topic name for an event
     *
     * @param string $eventClass
     * @return string
     */
    public function getEventTopic(string $eventClass): string
    {
        return $this->eventTopics[$eventClass] ?? $this->defaultEventTopic;
    }
}