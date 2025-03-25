<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Eloquent;

use Ody\DB\ConnectionManager;
use Ody\DB\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;

class ConnectionFactory
{
    /**
     * Create a new connection instance based on the configuration.
     *
     * @param array $config
     * @param string $name
     * @return MySqlConnection
     * @throws BorrowTimeoutException
     */
    public static function make(array $config, string $name = 'default'): MySqlConnection
    {
        ConnectionManager::initPool($config, $name);

        return new MySqlConnection(
            ConnectionManager::getConnection($name),
            $config['database'] ?? $config['db_name'] ?? '',
            $config['prefix'] ?? '',
            $config
        );
    }
}