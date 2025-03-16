<?php
namespace Ody\DB;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\Connector;

class Eloquent
{
    public static function boot($config): void
    {
        $capsule = new Capsule;
        $capsule->addConnection(
            (new Eloquent())->setConfig($config)
        );
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        if (config('database.connection_pool.enabled')) {
            Connection::resolverFor('mysql', function($connection, $database, $prefix, $config) {
                return new MySqlConnection($connection, $database, $prefix, $config);
            });
        }

        // set timezone for timestamps etc
        date_default_timezone_set('UTC');
    }

    public function setConfig(array $config): array
    {
        $config['driver'] = $config['adapter'];
        $config['database'] = $config['db_name'];
        return $config;
    }
}