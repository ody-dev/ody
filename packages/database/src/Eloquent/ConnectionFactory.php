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

use Ody\ConnectionPool\Pool\Exceptions\BorrowTimeoutException;
use Ody\DB\ConnectionManager;

class ConnectionFactory
{
    public function __construct(
        private ConnectionManager $connectionManager
    )
    {
    }

    /**
     * Create a new connection instance based on the configuration.
     *
     * @param array $config
     * @param string $name
     * @return MySqlConnection
     * @throws BorrowTimeoutException
     */
    public function make(array $config, string $name = 'default'): MySqlConnection
    {
        $this->connectionManager->getPool($config, $name);

        return new MySqlConnection(
            $this->connectionManager->getConnection($name, $config),
            $config['database'] ?? $config['db_name'] ?? '',
            $config['prefix'] ?? '',
            $config,
            $this->connectionManager
        );
    }
}