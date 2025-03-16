<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Ody\Monolog\Logger;
use Ody\DB\ConnectionPool\PDOConnectionFactory;
use Ody\DB\ConnectionPool\ConnectionPoolFactory;
use SMProxy\Handler\Frontend\FrontendAuthenticator;
use SMProxy\Handler\Frontend\FrontendConnection;
use SMProxy\Log\Log;
use SMProxy\MysqlPacket\AuthPacket;
use SMProxy\MysqlPacket\BinaryPacket;
use SMProxy\MysqlPacket\MySQLPacket;
use SMProxy\MysqlPacket\MySqlPacketDecoder;
use SMProxy\MysqlPacket\OkPacket;
use SMProxy\MysqlPacket\Util\RandomUtil;
use SMProxy\Parser\ServerParse;
use SMProxy\Route\RouteService;
use Swoole\Coroutine;
use Swoole\Server;
use function SMProxy\Helper\array_copy;
use function SMProxy\Helper\getBytes;
use function SMProxy\Helper\getMysqlPackSize;
use function SMProxy\Helper\getString;

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

    public $source = [];

    public function start()
    {
        $host = config('pool.host');
        $port = config('pool.port');
        $mysqlHost = config('database.host');
        $mysqlPort = config('database.port');
        $server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $server->set(config('pool.additional'));
        $server->on('start', function (Server $server) use ($host, $port) {
            Logger::write('info', "server started; listening on tcp://$host:$port");
        });

        $server->on('workerStart', function (Server $server, int $workerId) use ($mysqlHost, $mysqlPort) {
            Logger::write('info', "starting worker $workerId");


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


        $server->on('connect', function (Server $server, int $fd, int $reactorId) use ($mysqlHost, $mysqlPort) {
            Logger::write('info', "new connection established; $fd");

            var_dump('send handshake');
            $Authenticator = new FrontendAuthenticator();
            $this->source[$fd] = $Authenticator;
            if ($server->exist($fd)) {
                $server->send($fd, $Authenticator->getHandshakePacket($fd));

                return;
            }

            $server->close($fd);
        });

        $server->on('receive', function ($server, int $fd, int $reactor_id, string $data) use ($mysqlHost, $mysqlPort) {
            Logger::write('info', "received request; $fd, $reactor_id");
            Logger::write('info', "Received from client FD $fd: " . bin2hex($data));

            $bin = (new MySqlPacketDecoder())->decode($data);

            if (!$this->source[$fd]->auth) {
                $this->auth($bin, $server, $fd);
                return;
            }

            if ($this->source[$fd]->auth) {
//                var_dump($data, $bin);

                $connection = $this->pool->borrow();
//                $result = $connection->query($this->extractQuery($data));

//                $stm = $connection->prepare($result->queryString);
//                $stm->execute();
//
//                $result = $stm->fetchAll(PDO::FETCH_ASSOC);

                $query = substr($data, 1);
                var_dump($query);
                if (str_contains($query, 'select')) {
                    $stmt = $connection->query("select * from `users`");
                    $stmt->execute();

                    $columns = $stmt->getColumnMeta(0);
                    $server->send($fd, generateColumnDefinitionPacket($columns));

//                    $columns = $stmt->getColumnMeta(0);
                    var_dump($stmt->fetch(PDO::FETCH_ASSOC));
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        var_dump(generateRowDataPacket($row));
                        $server->send($fd, generateRowDataPacket($row));
                    }
//                    $server->send($fd, generateColumnDefinitionPacket($columns));

                    // Send the COM_STMT_PREPARE response to the client
//                    $server->send($fd, $prepareResponse);
                    return;
                }

                $server->send($fd, generateOkPacket());

//                $server->send($fd, json_encode($result));
//                $server->close($fd);
            }

        });

        $server->start();
    }

    private function auth(BinaryPacket $bin, \Swoole\Server $server, int $fd)
    {
        if ($bin->data[0] == 20) {
            $checkAccount = $this->checkAccount($server, $fd, $this->source[$fd]->user, array_copy($bin->data, 4, 20));
            if (!$checkAccount) {
                $this->accessDenied($server, $fd, 4);
            } else {
                if ($server->exist($fd)) {
                    $server->send($fd, getString(OkPacket::$SWITCH_AUTH_OK));
                }
                $this->source[$fd]->auth = true;
            }
        } elseif ($bin->data[4] == 14) {
            if ($server->exist($fd)) {
                $server->send($fd, getString(OkPacket::$OK));
            }
        } else {
            $authPacket = new AuthPacket();
            $authPacket->read($bin);
            $checkAccount = true; // $this->checkAccount($server, $fd, $authPacket->user ?? '', $authPacket->password ?? []);
            if (!$checkAccount) {
                if ($authPacket->pluginName == 'mysql_native_password') {
                    $this->accessDenied($server, $fd, 2);
                } else {
                    $this->source[$fd]->user = $authPacket->user;
                    $this->source[$fd]->database = $authPacket->database;
                    $this->source[$fd]->seed = RandomUtil::randomBytes(20);
                    $authSwitchRequest = array_merge(
                        [254],
                        getBytes('mysql_native_password'),
                        [0],
                        $this->source[$fd]->seed,
                        [0]
                    );
                    if ($server->exist($fd)) {
                        $server->send($fd, getString(array_merge(getMysqlPackSize(count($authSwitchRequest)), [2], $authSwitchRequest)));
                    }
                }
            } else {
                if ($server->exist($fd)) {
                    $server->send($fd, getString(OkPacket::$AUTH_OK));
                }
                $this->source[$fd]->auth = true;
                $this->source[$fd]->database = $authPacket->database;
            }
        }
    }

    public function extractQuery(string $data): ?string {
        if (preg_match("/\x03(SELECT|select|INSERT|insert|UPDATE|DELETE|SHOW|DROP|CREATE|ALTER|SET|USE) (.*)/is", $data, $matches)) {
            return trim($matches[1] . ' ' . $matches[2]);
        }
        return null;
    }
}

(new PoolServer())->start();

function generateRowDataPacket(array $row): string {
    $packet = '';

    // Loop through each column in the row
    foreach ($row as $value) {
        // Example: Handle a non-NULL value
        if ($value === null) {
            // For NULL values, MySQL sends a 0xFB (column is NULL)
            $packet .= "\xFB"; // NULL indicator
        } else {
            // For non-NULL values, we first send the length of the value
            $packet .= pack('C', strlen($value)); // Length of the value (1 byte)
            $packet .= $value; // Actual value
        }
    }

    var_dump($packet);

    return $packet;
}

function generateColumnDefinitionPacket(array $column): string {
    // Start with the Catalog field (assumed to be 'def' for MySQL)
    $packet = "\x64\x65\x66\x00"; // "def\0"

    // Add Database (assuming a default of 'test')
    $packet .= pack('C', strlen('ody')) . 'ody';

    // Add Table name (assuming 'users')
    $packet .= pack('C', strlen('users')) . 'users';

    // Add Original Table name (assuming 'users')
    $packet .= pack('C', strlen('users')) . 'users';

    // Add Column name (from $column)
    $columnName = $column['name'];
    $packet .= pack('C', strlen($columnName)) . $columnName;

    // Add Original Column name (assuming same as column name)
    $packet .= pack('C', strlen($columnName)) . $columnName;

    // Length of column value (let's assume VARCHAR(255) for simplicity)
    $packet .= pack('V', 255); // For example, length is 255 bytes

    // Column Type (assuming VARCHAR type, which is 0x0F)
    $packet .= pack('C', 0x0F); // 0x0F for VARCHAR

    // Flags (example: NOT NULL flag, 0x01)
    $packet .= pack('v', 0x01); // Flags (2 bytes)

    // Decimals (usually 0 for non-decimal columns)
    $packet .= pack('C', 0); // Decimals (1 byte)

    // Return the final packet
    return $packet;
}

// 🔹 Generates a Fake MySQL Handshake Packet
function generateHandshakePacket(): string {
    return "\x0a5.7.32\x00\x26\x00\x00\x00mysql_native_password\x00";
}

// 🔹 Generates an OK Packet (Authentication Success)
function generateOkPacket(): string {
    return getString(OkPacket::$OK); // MySQL OK packet
}

// 🔹 Generates an Error Packet (Authentication Failure)
function generateErrorPacket(string $message): string {
    return "\xFF\x15\x04" . $message; // MySQL error format (simplified)
}

// 🔹 Extracts Username & Password from the MySQL Client Authentication Packet
function extractClientAuth(string $data): ?array {
    if (preg_match("/\x85\xae\x00\x00\x00(?:\x00){23}([^\x00]+)\x00([^\x00]+)/s", $data, $matches)) {
        return [$matches[1], $matches[2]];
    }
    return null;
}

// 🔹 Extracts SQL Query from Raw MySQL Client Packet
function extractQuery(string $data): ?string {
    if (preg_match("/\x03(SELECT|INSERT|UPDATE|DELETE|SHOW|DROP|CREATE|ALTER|SET) (.*)/is", $data, $matches)) {
        return trim($matches[1] . ' ' . $matches[2]);
    }
    return null;
}

// 🔹 Formats MySQL Query Response
function formatMysqlResponse(array $result): string {
    return json_encode($result); // Mock MySQL response in JSON format
}





