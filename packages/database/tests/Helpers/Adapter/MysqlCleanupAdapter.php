<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\DB\Tests\Helpers\Adapter;

use PDO;
use Ody\DB\Migrations\Database\Adapter\MysqlAdapter;

final class MysqlCleanupAdapter implements CleanupInterface
{
    private MysqlAdapter $mysqlAdapter;

    public function __construct(PDO $pdo)
    {
        $this->mysqlAdapter = new MysqlAdapter($pdo);
    }

    public function cleanupDatabase(): void
    {
        $database = getenv('ODY_MYSQL_DATABASE');
        $charset = getenv('ODY_MYSQL_CHARSET');
        $collate = getenv('ODY_MYSQL_COLLATE');

        $this->mysqlAdapter->query(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
        $this->mysqlAdapter->query(sprintf('CREATE DATABASE `%s` DEFAULT CHARACTER SET %s DEFAULT COLLATE %s', $database, $charset, $collate));
    }
}
