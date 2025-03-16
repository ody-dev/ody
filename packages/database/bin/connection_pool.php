<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Ody\Monolog\Logger;
use Ody\DB\ConnectionPool\PDOConnectionFactory;
use Ody\DB\ConnectionPool\ConnectionPoolFactory;
use Swoole\Coroutine;
use Swoole\Server;

const PROJECT_PATH = __DIR__ . "/../";

//$server = ServerManager::init(ServerType::HTTP_SERVER)
//    ->createServer(config('server'))
//    ->setServerConfig(config('server.additional'))
//    ->registerCallbacks(config('server.callbacks'))
//    ->daemonize($input->getOption('daemonize'))
//    ->getServerInstance();

class PoolServer
{
    public $pool;

    public function start()
    {
        $host = config('pool.host');
        $port = config('pool.port');
        $server = new Server($host, $port);
        $server->set(config('pool.additional'));
        $server->on('start', function (Server $server) use ($host, $port) {
            Logger::write('info', "server started; listening on tcp://$host:$port");
        });

        $server->on('workerStart', function (Server $server, int $workerId) {
            Logger::write('info', "starting worker $workerId");

            $mysqlHost = config('database.host');
            $mysqlPort = config('database.port');
            $dbName = config('database.db_name');
            $connectionPoolFactory = ConnectionPoolFactory::create(
                size: 60,
                factory: new PDOConnectionFactory(
                    dsn: "mysql:host=$mysqlHost;port=$mysqlPort;dbname=$dbName",
                    username: config('database.username'),
                    password: config('database.password'),
                ),
            );

            $this->pool = $connectionPoolFactory->instantiate();
        });


        $server->on('connect', function (Server $server, int $fd, int $reactorId) {
            $clientIp = $server->getClientInfo($fd, $reactorId)['remote_ip'];
            if (in_array($clientIp, config('pool.allowed_ips'))) {
                Logger::write('info', "new connection established; $fd");
                $server->confirm($fd);

                return;
            }

            $server->close($fd);
        });

        $server->on('receive', function ($server, int $fd, int $reactor_id, string $data)
        {
            Logger::write('info', "received request; $fd, $reactor_id");
            Logger::write('info', "query: $data");

            try {
                $data = json_decode(openssl_decrypt($data,"AES-128-ECB", config('database.key')), true);

                $connection = $this->pool->borrow();
                $stm = $connection->prepare($data[0]);
                $stm->execute($data[1]);

                $result = $stm->fetchAll(PDO::FETCH_ASSOC);

                $server->send($fd, json_encode($result));
                $server->close($fd);
            } catch (\Throwable $exception) {
                Logger::write('error',  'message: ' . $exception->getMessage() .  " [{$exception->getFile()}:{$exception->getLine()}]");

                $server->send($fd, json_encode(['error' => $exception->getMessage() .  " [{$exception->getFile()}:{$exception->getLine()}]"]));
                $server->close($fd);
            }
        });

        $server->start();
    }
}

(new PoolServer())->start();




