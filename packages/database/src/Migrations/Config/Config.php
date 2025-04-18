<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Migrations\Config;

use Ody\DB\Migrations\Exception\ConfigException;
use Ody\DB\Migrations\Exception\InvalidArgumentValueException;

final class Config
{
    /**
     * @var array<string, mixed>
     */
    private array $configuration = [
        'log_table_name' => 'migrations_log',
        'migration_dirs' => [],
        'environments' => [],
        'default_environment' => '',
        'dependencies' => [],
        'template' => __DIR__ . '/../Templates/DefaultTemplate.ody',
        'indent' => '4spaces',
    ];

    /**
     * @param array<string, mixed> $configuration
     * @throws ConfigException
     */
    public function __construct(array $configuration)
    {
        $this->configuration = array_merge($this->configuration, $configuration);
        if (empty($this->configuration['migration_dirs'])) {
            throw new ConfigException('Empty migration dirs');
        }

        if (empty($this->configuration['environments'])) {
            throw new ConfigException('Empty environments');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return array<int|string, string>
     */
    public function getMigrationDirs(): array
    {
        return $this->configuration['migration_dirs'];
    }

    /**
     * @throws InvalidArgumentValueException
     */
    public function getMigrationDir(?string $dir = null): string
    {
        if ($dir === null) {
            if (count($this->configuration['migration_dirs']) > 1) {
                throw new InvalidArgumentValueException('There are more then 1 migration dirs. Use one of them: ' . implode(', ', array_keys($this->configuration['migration_dirs'])));
            }
            return current($this->configuration['migration_dirs']);
        }

        if (isset($this->configuration['migration_dirs'][$dir])) {
            return $this->configuration['migration_dirs'][$dir];
        }

        throw new InvalidArgumentValueException('Directory "' . $dir . '" doesn\'t exist. Use: ' . implode(', ', array_keys($this->configuration['migration_dirs'])));
    }

    public function getLogTableName(): string
    {
        return $this->configuration['log_table_name'];
    }

    public function getDefaultEnvironment(): string
    {
        if ($this->configuration['default_environment']) {
            return $this->configuration['default_environment'];
        }
        return (string)current(array_keys($this->configuration['environments']));
    }

    public function getEnvironmentConfig(string $environment): ?EnvironmentConfig
    {
        return isset($this->configuration['environments'][$environment]) ? new EnvironmentConfig($this->configuration['environments'][$environment]) : null;
    }

    /**
     * @throws InvalidArgumentValueException
     */
    public function getDependency(string $type): object
    {
        if (isset($this->configuration['dependencies'][$type])) {
            return $this->configuration['dependencies'][$type];
        }
        throw new InvalidArgumentValueException('Dependency for type "' . $type . '" not found. Register it via $configuration[\'dependencies\'][\'' . $type . '\']');
    }

    public function getTemplate(): string
    {
        return $this->configuration['template'];
    }

    public function getIndent(): string
    {
        return $this->configuration['indent'];
    }
}
