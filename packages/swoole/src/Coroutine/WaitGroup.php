<?php

namespace Ody\Swoole\Coroutine;

use BadMethodCallException;
use InvalidArgumentException;
use Swoole\Coroutine\Channel;

class WaitGroup
{
    protected Channel $chan;

    protected int $count = 0;

    protected bool $waiting = false;

    public function __construct(int $delta = 0)
    {
        $this->chan = new Channel(1);
        if ($delta > 0) {
            $this->add($delta);
        }
    }

    public function add(int $delta = 1): void
    {
        if ($this->waiting) {
            throw new BadMethodCallException('WaitGroup misuse: add called concurrently with wait');
        }
        $count = $this->count + $delta;
        if ($count < 0) {
            throw new InvalidArgumentException('WaitGroup misuse: negative counter');
        }
        $this->count = $count;
    }

    public function done(): void
    {
        $count = $this->count - 1;
        if ($count < 0) {
            throw new BadMethodCallException('WaitGroup misuse: negative counter');
        }
        $this->count = $count;
        if ($count === 0 && $this->waiting) {
            $this->chan->push(true);
        }
    }

    public function wait(float $timeout = -1): bool
    {
        if ($this->waiting) {
            throw new BadMethodCallException('WaitGroup misuse: reused before previous wait has returned');
        }
        if ($this->count > 0) {
            $this->waiting = true;
            $done          = $this->chan->pop($timeout);
            $this->waiting = false;
            return $done;
        }
        return true;
    }

    public function count(): int
    {
        return $this->count;
    }
}