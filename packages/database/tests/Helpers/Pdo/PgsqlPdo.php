<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Helpers\Pdo;

use PDO;

final class PgsqlPdo extends PDO
{
    public function __construct(?string $db = null)
    {
        $dsn = 'pgsql:';
        if ($db) {
            $dsn .= 'dbname=' . $db;
        }
        if (getenv('ODY_PGSQL_HOST')) {
            $dsn .= ';host=' . getenv('ODY_PGSQL_HOST');
        }
        if (getenv('ODY_PGSQL_PORT')) {
            $dsn .= ';port=' . getenv('ODY_PGSQL_PORT');
        }
        if (getenv('ODY_PGSQL_CHARSET')) {
            $dsn .= ';options=\'--client_encoding=' . getenv('ODY_PGSQL_CHARSET') . '\'';
        }
        parent::__construct($dsn, getenv('ODY_PGSQL_USERNAME'), getenv('ODY_PGSQL_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
        ]);
    }
}
