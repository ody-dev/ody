<?php

use Doctrine\DBAL\DriverManager;
use Ody\DB\Doctrine\DBALMysQLDriver;

DBALMySQLDriver::initializePool(poolSize: 10);

// Configure Doctrine DBAL to use your custom driver
$connectionParams = [
    'driverClass' => DBALMySQLDriver::class,
    'dbname' => 'your_database',
    'user' => 'username',
    'password' => 'password',
    'host' => 'localhost',
];

$connection = DriverManager::getConnection($connectionParams);