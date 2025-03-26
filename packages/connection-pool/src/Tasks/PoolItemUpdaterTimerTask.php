<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\ConnectionPool\Tasks;

use Psr\Log\LoggerInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\PoolItemState;
use Ody\ConnectionPool\ConnectionPool\Pool\PoolItemWrapperInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\TimerTask\TimerTaskInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\Exceptions\PoolItemRemovedException;
use Ody\ConnectionPool\ConnectionPool\Pool\Exceptions\PoolItemCreationException;

use function is_null;

/**
 * @template TItem of object
 * @implements TimerTaskInterface<PoolItemWrapperInterface<TItem>>
 */
readonly class PoolItemUpdaterTimerTask implements TimerTaskInterface
{
    public function __construct(
        public float $intervalSec,
        public float $maxLifetimeSec,
        public LoggerInterface $logger,
        public float $maxItemReservingWaitingTimeSec = .0,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function run(int $timerId, mixed $runnerRef): void
    {
        /** @var PoolItemWrapperInterface<TItem>|null $runner */
        $runner = $runnerRef->get();

        if (is_null($runner)) {
            return;
        }

        if ($this->maxItemReservingWaitingTimeSec == .0) {
            $isReserved = $runner->compareAndSetState(
                expect: PoolItemState::IDLE,
                update: PoolItemState::RESERVED,
            );
        } else {
            $isReserved = $runner->waitForCompareAndSetState(
                expect: PoolItemState::IDLE,
                update: PoolItemState::RESERVED,
                timeoutSec: $this->maxItemReservingWaitingTimeSec,
            );
        }

        if (!$isReserved) {
            return;
        }

        $logContext = ['item_id' => $runner->getId()];

        if ($runner->stats()['item_lifetime_sec'] > $this->maxLifetimeSec) {
            try {
                $runner->recreateItem();
            } catch (PoolItemCreationException $exception) {
                $this->logger->error('Can\'t recreate item: ' . $exception->getMessage(), $logContext);
            }
        }

        try {
            $runner->setState(PoolItemState::IDLE);
        } catch (PoolItemRemovedException) {
            $this->logger->info('Can\'t set IDLE state (item already removed)', $logContext);
        }
    }

    public function getIntervalSec(): float
    {
        return $this->intervalSec;
    }
}
