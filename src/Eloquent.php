<?php
namespace Ody\DB;

use Illuminate\Database\Capsule\Manager as Capsule;

class Eloquent
{
    public static function boot($config)
    {
        $capsule = new Capsule;
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // set timezone for timestamps etc
        date_default_timezone_set('UTC');
    }
}