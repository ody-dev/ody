<?php

namespace Ody\DB;

use Illuminate\Database\Connection as BaseConnection;
use Swoole\Coroutine;

class Connection extends BaseConnection
{
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $this->poolHost = config('database.connection_pool.host');
        $this->poolPort = config('database.connection_pool.port');
        $this->poolEnabled = config('database.connection_pool.enabled');
    }

    public function select($query, $bindings = array(), $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            return $this->sendToConnectionPool([$query, $bindings]);
        });
    }

//    public function selectOne($query, $bindings = array(), $useReadPdo = true)
//    {
//        // This method is pretty much straight forward. Call the
//        // parent::select() method. If it returns any results
//        // normalize the first result or else return null.
//        $records = parent::select($query, $bindings);
//
//        if (count($records) > 0)
//        {
//            return with(new Normalizer)->normalize(reset($records));
//        }
//
//        return null;
//    }

    private function sendToConnectionPool(array $data)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
        }

        $result = socket_connect($socket, $this->poolHost, $this->poolPort);
        if ($result === false) {
            Throw new ConnectionPoolException("connect to pool failed. Error: " . socket_strerror(socket_last_error($socket)));
        }

        socket_write($socket, openssl_encrypt(json_encode($data),"AES-128-ECB", config('app.key')));
        $result = '';
        while ($out = socket_read($socket, 2048)) {
            $result .= $out;
        }

        socket_close($socket);

        return json_decode($result, true);


//        $client = new Client(SWOOLE_SOCK_TCP);
//        if (!$client->connect($this->host, $this->port, 0.5)) {
//            Logger::write('error', "connect to pool failed. Error: {$client->errCode}");
//            Throw new ConnectionPoolException("connect to pool failed. Error: {$client->errCode}");
//        }
//
//        $client->send($statement->queryString);
//        $result = $client->recv();
//        $client->close();
//
//        return json_decode($result, true);
    }
}