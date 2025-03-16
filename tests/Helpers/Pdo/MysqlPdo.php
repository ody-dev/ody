<?php

declare(strict_types=1);

namespace Ody\DB\Tests\Helpers\Pdo;

use PDO;

final class MysqlPdo extends PDO
{
    public function __construct(?string $db = null)
    {
        $dsnParts = [];
        if ($db) {
            $dsnParts[] = 'dbname=' . $db;
        }
        if (getenv('ODY_MYSQL_HOST')) {
            $dsnParts[] = 'host=' . getenv('ODY_MYSQL_HOST');
        }
        if (getenv('ODY_MYSQL_PORT')) {
            $dsnParts[] = 'port=' . getenv('ODY_MYSQL_PORT');
        }
        if (getenv('ODY_MYSQL_CHARSET')) {
            $dsnParts[] = 'charset=' . getenv('ODY_MYSQL_CHARSET');
        }

        $dsn = 'mysql:' . implode(';', $dsnParts);
        parent::__construct($dsn, getenv('ODY_MYSQL_USERNAME'), getenv('ODY_MYSQL_PASSWORD') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
        ]);
    }
}
