<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB;

use Composer\Script\Event;
use Composer\IO\IOInterface;

/**
 * Installer class for handling post-installation setup
 */
class Installer
{
    /**
     * Prompt the user to choose a database provider
     *
     * @param Event $event Composer event
     * @return void
     */
    public static function promptForProvider(Event $event): void
    {
        $io = $event->getIO();

        // Only prompt if this is an interactive terminal
        if (!$io->isInteractive()) {
            $io->write('<info>Skipping database provider selection (non-interactive mode)</info>');
            return;
        }

        $io->write([
            '',
            '<comment>Ody Database Provider Setup</comment>',
            'Please select your preferred database ORM:',
            '',
        ]);

        $options = [
            1 => 'Eloquent ORM (Laravel\'s ORM with ActiveRecord pattern)',
            2 => 'Doctrine ORM (ORM with DataMapper pattern)',
            3 => 'Doctrine DBAL (Database Abstraction Layer)',
            0 => 'None (basic PDO support only)',
        ];

        foreach ($options as $key => $description) {
            $io->write("  [{$key}] <info>{$description}</info>");
        }

        $io->write('');

        // Get user choice
        $choice = $io->ask('Select an option [<comment>1</comment>]: ', '1');

        if (!isset($options[(int)$choice])) {
            $io->write('<error>Invalid selection. Defaulting to Eloquent ORM.</error>');
            $choice = 1;
        } else {
            $choice = (int)$choice;
        }

        // Define package requirements for each option
        $packages = [];

        switch ($choice) {
            case 0: // None
                $io->write('<info>Setting up basic PDO support only.</info>');
                break;

            case 1: // Eloquent
                $io->write('<info>Setting up Eloquent ORM.</info>');
                $packages = ['illuminate/database:^11.0'];
                break;

            case 2: // Doctrine
                $io->write('<info>Setting up Doctrine ORM.</info>');
                $packages = ['doctrine/orm:^3.0', 'doctrine/dbal:^4.0'];
                break;

            case 3: // Both
                $io->write('<info>Setting up both Doctrine DBAL.</info>');
                $packages = ['doctrine/dbal:^4.0'];
                break;
        }

        // Install required packages
        if (!empty($packages)) {
            $io->write('<info>Installing required packages...</info>');

            $composer = $event->getComposer();
            $installationManager = $composer->getInstallationManager();
            $repositoryManager = $composer->getRepositoryManager();
            $localRepo = $repositoryManager->getLocalRepository();

            $io->write([
                '',
                '<comment>Please run the following command to install required packages:</comment>',
                '',
                '    composer require ' . implode(' ', $packages),
                '',
            ]);
        }

        // Suggest next steps based on selection
        $io->write([
            '',
            '<info>Setup instructions:</info>',
            '',
        ]);

        if ($choice === 0 || $choice === 3) {
            $io->write([
                '1. Make sure to register <comment>Ody\DB\Providers\DatabaseServiceProvider</comment> in your application',
                '2. Publish the configuration with: <comment>php ody vendor ody/database</comment>',
            ]);
        } else if ($choice === 1) {
            $io->write([
                '1. Register these providers in your application:',
                '   - <comment>Ody\DB\Providers\DatabaseServiceProvider</comment>',
                '   - <comment>Ody\DB\Providers\EloquentServiceProvider</comment>',
                '2. Publish the configuration with: <comment>php ody vendor ody/database</comment>',
            ]);
        } else if ($choice === 2) {
            $io->write([
                '1. Register these providers in your application:',
                '   - <comment>Ody\DB\Providers\DatabaseServiceProvider</comment>',
                '   - <comment>Ody\DB\Providers\DBALServiceProvider</comment>',
                '   - <comment>Ody\DB\Providers\DoctrineORMServiceProvider</comment>',
                '2. Publish the configuration with: <comment>php artisan publish ody/database</comment>',
                '3. Publish Doctrine configuration: <comment>php artisan publish ody/database doctrine</comment>',
            ]);
        }

        $io->write([
            '',
            'To enable connection pooling with, set <comment>enable_connection_pool</comment> to <comment>true</comment> in your database configuration.',
            '',
            '<info>Setup complete!</info>',
        ]);
    }
}