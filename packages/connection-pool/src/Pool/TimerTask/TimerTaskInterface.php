<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\ConnectionPool\Pool\TimerTask;

/**
 * @template TRunner of object
 */
interface TimerTaskInterface
{
    /**
     * @param  \WeakReference<TRunner>  $runnerRef
     */
    public function run(int $timerId, mixed $runnerRef): void;

    public function getIntervalSec(): float;
}
