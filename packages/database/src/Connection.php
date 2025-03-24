<?php

namespace Ody\DB;

use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function select($query, $bindings = array(), $useReadPdo = true)
    {
        return parent::select($query, $bindings, $useReadPdo);
    }

    public function selectOne($query, $bindings = array(), $useReadPdo = true)
    {
        return parent::select($query, $bindings);
    }
}