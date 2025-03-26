<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\Tasks;

use Ody\ConnectionPool\KeepaliveCheckerInterface;
use Ody\ConnectionPool\Pool\Exceptions\PoolItemCreationException;
use Ody\ConnectionPool\Pool\Exceptions\PoolItemRemovedException;
use Ody\ConnectionPool\Pool\PoolItemState;
use Ody\ConnectionPool\Pool\PoolItemWrapperInterface;
use Ody\ConnectionPool\Pool\TimerTask\TimerTaskInterface;
use Psr\Log\LoggerInterface;
use function is_null;

/**
 * @template TItem of object
 * @implements TimerTaskInterface<PoolItemWrapperInterface<TItem>>
 */
readonly class KeepaliveCheckTimerTask implements TimerTaskInterface
{
    /**
     * @param  KeepaliveCheckerInterface<TItem>  $keepaliveChecker
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected KeepaliveCheckerInterface $keepaliveChecker,
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

        if (!$runner->compareAndSetState(PoolItemState::IDLE, PoolItemState::RESERVED)) {
            return;
        }

        $isAlive = $this->keepaliveChecker->check($runner->getItem());
        $logContext = ['item_id' => $runner->getId()];

        if (!$isAlive) {
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
        return $this->keepaliveChecker->getIntervalSec();
    }
}
