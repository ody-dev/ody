<?php

namespace Ody\Scheduler;

use Ody\Swoole\Process\Exception;
use Ody\Swoole\Process\Socket\AbstractUnixProcess;
use Ody\Scheduler\Protocol\Pack;
use Ody\Scheduler\Protocol\Command;
use Ody\Scheduler\Protocol\Response;
use Swoole\Coroutine\Socket;
use Swoole\Table;

class Worker extends AbstractUnixProcess
{

    private $crontabInstance;


    private Table $workerStatisticTable;

    private array $jobs = [];

    private int $workerIndex = 0;


    private Table $schedulerTable;

    /**
     * @param $arg
     * @return void
     * @throws Exception
     */
    public function run($arg): void
    {
        $this->crontabInstance = $arg['crontabInstance'];
        $this->workerStatisticTable = $arg['workerStatisticTable'];
        $this->workerIndex = $arg['workerIndex'];
        $this->workerStatisticTable->set($this->workerIndex, [
            'runningNum' => 0
        ]);
        $this->schedulerTable = $arg['schedulerTable'];
        $this->jobs = $arg['jobs'];
        parent::run($arg);
    }

    /**
     * @param Socket $socket
     * @return void
     * @throws \Throwable
     */
    function onAccept(Socket $socket): void
    {
        $response = new Response();

        $header = $socket->recvAll(4, 1);
        if (strlen($header) != 4) {
            $response->setStatus(Response::STATUS_PACKAGE_READ_TIMEOUT)->setMsg('recv from client timeout');
            $this->reply($socket, $response);
            return;
        }
        $allLength = Pack::packDataLength($header);
        $data = $socket->recvAll($allLength, 1);
        if (strlen($data) != $allLength) {
            $response->setStatus(Response::STATUS_PACKAGE_READ_TIMEOUT)->setMsg('recv from client timeout');
            $this->reply($socket, $response);
            return;
        }
        $data = unserialize($data);
        if (!$data instanceof Command) {
            $response->setStatus(Response::STATUS_ILLEGAL_PACKAGE)->setMsg('unserialize request as an Command instance fail');
            $this->reply($socket, $response);
            return;
        }
        if ($data->getCommand() === Command::COMMAND_EXEC_JOB) {
            $jobName = $data->getArg();
            if (isset($this->jobs[$jobName])) {
                /** @var JobInterface $job */
                $job = $this->jobs[$jobName];
                $this->workerStatisticTable->incr($this->workerIndex, 'runningNum', 1);
                $this->schedulerTable->incr($jobName, 'taskRunTimes', 1);
                $this->schedulerTable->set($jobName, ['taskCurrentRunTime' => time()]);
                try {
                    $ret = $job->run();
                    $response->setResult($ret);
                    $response->setStatus(Response::STATUS_OK);
                } catch (\Throwable $throwable) {
                    $response->setStatus(Response::STATUS_JOB_EXEC_ERROR);
                    try {
                        $job->onException($throwable);
                    } catch (\Throwable $t) {
                        $call = $this->crontabInstance->getConfig()->getOnException();
                        if (is_callable($call)) {
                            call_user_func($call, $t);
                        } else {
                            throw $t;
                        }
                    }
                } finally {
                    $this->workerStatisticTable->decr($this->workerIndex, 'runningNum', 1);
                    $this->reply($socket, $response);
                }
            } else {
                $response->setStatus(Response::STATUS_JOB_NOT_EXIST);
                $this->reply($socket, $response);
            }
        } else {
            $response->setStatus(Response::STATUS_UNKNOWN_COMMAND);
            $this->reply($socket, $response);
        }
    }

    /**
     * @param Socket $socket
     * @param Response $response
     * @return void
     */
    private function reply(Socket $socket, Response $response): void
    {
        $data = serialize($response);
        $socket->sendAll(Pack::pack($data));
        $socket->close();
    }
}