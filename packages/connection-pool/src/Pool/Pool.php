<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\Pool;

use LogicException;
use Ody\ConnectionPool\Pool\Hook\PoolItemHook;
use Ody\ConnectionPool\Pool\Hook\PoolItemHookManagerInterface;
use Ody\ConnectionPool\Pool\TimerTask\TimerTaskSchedulerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use SplObjectStorage;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;
use WeakReference;
use function array_key_exists;
use function hrtime;
use function is_null;
use function max;
use function sprintf;

/**
 * @template TItem of object
 *
 * @implements PoolInterface<TItem>
 * @implements PoolControlInterface<TItem>
 */
class Pool implements PoolInterface, PoolControlInterface
{
    protected PoolMetrics $metrics;

    /** @var SplObjectStorage<TItem, PoolItemWrapperInterface<TItem>> */
    protected SplObjectStorage $borrowedItemStorage;

    /** @var SplObjectStorage<PoolItemWrapperInterface<TItem>, float> */
    protected SplObjectStorage $idledItemStorage;

    protected Channel $concurrentBag;

    /** @var array<int, TItem> */
    protected array $itemToCoroutineBindings;

    protected int $itemWrapperCount;

    /**
     * @param  non-empty-string                                               $name
     * @param  PoolItemWrapperFactoryInterface<TItem>                         $poolItemWrapperFactory
     * @param  TimerTaskSchedulerInterface<PoolControlInterface<TItem>>|null  $timerTaskScheduler
     * @param  PoolItemHookManagerInterface<TItem>|null                       $poolItemHookManager
     */
    public function __construct(
        protected string $name,
        protected PoolConfig $config,
        protected PoolItemWrapperFactoryInterface $poolItemWrapperFactory,
        protected LoggerInterface $logger,
        protected ?TimerTaskSchedulerInterface $timerTaskScheduler = null,
        protected ?PoolItemHookManagerInterface $poolItemHookManager = null,
    ) {
        $this->metrics = new PoolMetrics();
        $this->concurrentBag = new Channel($config->size);
        $this->itemWrapperCount = 0;
        $this->idledItemStorage = new SplObjectStorage();
        $this->borrowedItemStorage = new SplObjectStorage();
        $this->itemToCoroutineBindings = [];

        $this->timerTaskScheduler?->bindTo($this);
        $this->timerTaskScheduler?->run();

        $this->timerTaskScheduler?->start();
    }

    public function __destruct()
    {
        $this->timerTaskScheduler?->stop();

        // @phpstan-ignore-next-line
        $this->idledItemStorage->removeAll($this->idledItemStorage);

        // @phpstan-ignore-next-line
        $this->borrowedItemStorage->removeAll($this->borrowedItemStorage);

        $this->concurrentBag->close();
    }

    /**
     * @inheritDoc
     */
    public function borrow(): mixed
    {
        $cid = getmypid() . '-' . Coroutine::getCid();

        if ($this->config->bindToCoroutine && array_key_exists($cid, $this->itemToCoroutineBindings)) {
            return $this->itemToCoroutineBindings[$cid];
        }

        $start = hrtime(true);

        $poolItemWrapper = $this->getReservedPoolItemWrapperWithExistingItem(
            timeLeftSec: $this->config->borrowingTimeoutSec,
            increaseItemsOnEmptyPool: true,
        );

        $this->poolItemHookManager?->run(PoolItemHook::BEFORE_BORROW, $poolItemWrapper);

        if (!$poolItemWrapper->compareAndSetState(PoolItemState::RESERVED, PoolItemState::IN_USE)) {
            throw new LogicException();
        }

        $item = $poolItemWrapper->getItem();

        // todo: in this case it's probably better to try getting a new pool item wrapper
        if (is_null($item)) {
            throw new Exceptions\BorrowTimeoutException('Can\'t get item after hooks');
        }

        $this->idledItemStorage->detach($poolItemWrapper);
        $this->borrowedItemStorage->attach($item, $poolItemWrapper);

        if ($this->config->bindToCoroutine) {
            $this->itemToCoroutineBindings[$cid] = $item;
        }

        if ($this->config->autoReturn) {
            $itemRef = WeakReference::create($item);

            Coroutine::defer(function () use ($cid, $itemRef) {
                unset($this->itemToCoroutineBindings[$cid]);
                $item = $itemRef->get();

                $this->return($item);
            });
        }

        $this->metrics->borrowedTotal++;
        $this->metrics->waitingForItemBorrowingTotalSec += 1e-9 * (hrtime(true) - $start);

        return $item;
    }

    /**
     * @inheritDoc
     */
    public function return(mixed &$poolItemRef): void
    {
        $poolItemWrapper = $this->returnBorrowedItem($poolItemRef);

        if (is_null($poolItemWrapper)) {
            return;
        }

        if ($this->concurrentBag->isFull()) {
            return;
        }

        $this->metrics->itemInUseTotalSec += $poolItemWrapper->stats()['current_state_duration_sec'];

        if (is_null($this->poolItemHookManager)) {
            $poolItemWrapper->setState(PoolItemState::IDLE);
        } else {
            $poolItemWrapper->setState(PoolItemState::RESERVED);

            $this->poolItemHookManager->run(PoolItemHook::AFTER_RETURN, $poolItemWrapper);
            if (!$poolItemWrapper->compareAndSetState(PoolItemState::RESERVED, PoolItemState::IDLE)) {
                throw new LogicException();
            }
        }

        $this->idledItemStorage->attach($poolItemWrapper, hrtime(true));

        $isReturned = $this->concurrentBag->push($poolItemWrapper, $this->config->returningTimeoutSec);

        if (!$isReturned) {
            $this->idledItemStorage->detach($poolItemWrapper); // This line already exists
        }
    }

    /**
     * @inheritDoc
     */
    public function stats(): array
    {
        return [
            'all_item_count' => $this->getCurrentSize(),
            'idled_item_count' => $this->getIdleCount(),
            'borrowed_item_count' => $this->borrowedItemStorage->count(),
            /** @phpstan-ignore-next-line */
            'consumer_pending_count' => (int)$this->concurrentBag->stats()['consumer_num'],

            'borrowed_total' => $this->metrics->borrowedTotal,
            'item_created_total' => $this->metrics->itemCreatedTotal,
            'item_deleted_total' => $this->metrics->itemDeletedTotal,
            'borrowing_timeouts_total' => $this->metrics->borrowingTimeoutsTotal,

            'item_in_use_total_sec' => $this->metrics->itemInUseTotalSec,
            'item_creation_total_sec' => $this->metrics->itemCreationTotalSec,
            'waiting_for_item_borrowing_total_sec' => $this->metrics->waitingForItemBorrowingTotalSec,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getIdleCount(): int
    {
        return $this->concurrentBag->length();
    }

    public function getCurrentSize(): int
    {
        return $this->itemWrapperCount;
    }

    /**
     * @inheritDoc
     */
    public function getIdledItemStorage(): SplObjectStorage
    {
        return $this->idledItemStorage;
    }

    /**
     * @inheritDoc
     */
    public function getBorrowedItemStorage(): SplObjectStorage
    {
        return $this->borrowedItemStorage;
    }

    public function getConfig(): PoolConfig
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function increaseItems(): bool
    {
        if ($this->concurrentBag->isFull()) {
            return false;
        }

        $this->itemWrapperCount++;

        $start = hrtime(true);

        try {
            $poolItemWrapper = $this->poolItemWrapperFactory->create();
        } catch (Throwable $throwable) {
            $this->itemWrapperCount--;
            throw $throwable;
        }

        $this->metrics->itemCreatedTotal++;
        $this->metrics->itemCreationTotalSec += 1e-9 * (hrtime(true) - $start);

        $this->idledItemStorage->attach($poolItemWrapper, hrtime(true));

        $result = $this->concurrentBag->push($poolItemWrapper, .001);

        if ($result === false) {
            $this->removePoolItemWrapper($poolItemWrapper);
        }

        return $result;
    }

    public function decreaseItems(): bool
    {
        if ($this->concurrentBag->isEmpty()) {
            return false;
        }

        /** @var PoolItemWrapperInterface<TItem>|false $poolItemWrapper */
        $poolItemWrapper = $this->concurrentBag->pop(.001);

        if ($poolItemWrapper === false) {
            return false;
        }

        $this->removePoolItemWrapper($poolItemWrapper);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function removeItem(mixed &$poolItemRef): void
    {
        $poolItemWrapper = $this->returnBorrowedItem($poolItemRef);

        if (is_null($poolItemWrapper)) {
            return;
        }

        $this->metrics->itemInUseTotalSec += $poolItemWrapper->stats()['current_state_duration_sec'];

        $this->removePoolItemWrapper($poolItemWrapper);
    }

    /**
     * @param  TItem|null  $poolItemRef
     *
     * @return PoolItemWrapperInterface<TItem>|null
     */
    protected function returnBorrowedItem(mixed &$poolItemRef): ?PoolItemWrapperInterface
    {
        if ($poolItemRef === null) {
            return null;
        }

        $poolItem = $poolItemRef;
        $poolItemRef = null;

        if (!$this->borrowedItemStorage->contains($poolItem)) {
            return null;
        }

        /** @var PoolItemWrapperInterface<TItem> $poolItemWrapper */
        $poolItemWrapper = $this->borrowedItemStorage[$poolItem];

        $this->borrowedItemStorage->detach($poolItem);

        unset($this->itemToCoroutineBindings[getmypid() . '-' . Coroutine::getCid()]);

        if ($poolItemWrapper->getState() != PoolItemState::IN_USE) {
            throw new LogicException();
        }

        return $poolItemWrapper;
    }

    /**
     * @param  PoolItemWrapperInterface<TItem>  $poolItemWrapper
     */
    protected function removePoolItemWrapper(PoolItemWrapperInterface $poolItemWrapper): void
    {
        $this->idledItemStorage->detach($poolItemWrapper);

        $poolItemWrapper->close();

        $this->itemWrapperCount--;
        $this->metrics->itemDeletedTotal++;
    }

    /**
     * @return PoolItemWrapperInterface<TItem>
     * @throws Exceptions\BorrowTimeoutException
     */
    protected function getPoolItemWrapper(float $timeLeftSec, bool $increaseItemsOnEmptyPool): PoolItemWrapperInterface
    {
        $isPoolEmpty = $this->concurrentBag->isEmpty() && $this->getCurrentSize() < $this->config->size;

        if ($increaseItemsOnEmptyPool && $isPoolEmpty) {
            \Swoole\Coroutine\go(function () {
                try {
                    $this->increaseItems();
                } catch (Throwable $exception) {
                    $errorMessage = sprintf(
                        'Can\'t create new item for empty pool (%s): %s',
                        (new ReflectionClass($exception))->getShortName(),
                        $exception->getMessage(),
                    );

                    $this->logger->error($errorMessage, ['pool_name' => $this->getName()]);
                }
            });
        }

        /** @var PoolItemWrapperInterface<TItem>|false $poolItemWrapper */
        $poolItemWrapper = $this->concurrentBag->pop($timeLeftSec);

        if ($poolItemWrapper === false) {
            $this->metrics->borrowingTimeoutsTotal++;

            throw new Exceptions\BorrowTimeoutException('Can\'t pop item from concurrentBag');
        }

        return $poolItemWrapper;
    }

    /**
     * @return PoolItemWrapperInterface<TItem>
     * @throws Exceptions\BorrowTimeoutException
     */
    protected function getReservedPoolItemWrapper(float $timeoutSec, bool $increaseItemsOnEmptyPool): PoolItemWrapperInterface
    {
        $start = hrtime(true);
        $poolItemWrapper = $this->getPoolItemWrapper($timeoutSec, $increaseItemsOnEmptyPool);
        $timeoutSec = max(.0001, $timeoutSec - (hrtime(true) - $start) * 1e-9);

        if (!$poolItemWrapper->waitForCompareAndSetState(PoolItemState::IDLE, PoolItemState::RESERVED, $timeoutSec)) {
            $context = [
                'pool_name' => $this->getName(),
                'item_id' => $poolItemWrapper->getId(),
                'item_old_state' => $poolItemWrapper->getState()->name,
                'item_new_state' => PoolItemState::RESERVED->name,
            ];
            $errorMessage = sprintf(
                'Can\'t set %s state (old state %s)',
                PoolItemState::RESERVED->name,
                $poolItemWrapper->getState()->name,
            );

            $this->logger->error($errorMessage, $context);

            $this->metrics->borrowingTimeoutsTotal++;

            $result = $this->concurrentBag->push($poolItemWrapper, .001);

            if ($result === false) {
                $this->removePoolItemWrapper($poolItemWrapper);
            }

            throw new Exceptions\BorrowTimeoutException($errorMessage);
        }

        return $poolItemWrapper;
    }

    /**
     * @return PoolItemWrapperInterface<TItem>
     * @throws Exceptions\BorrowTimeoutException
     */
    protected function getReservedPoolItemWrapperWithExistingItem(float $timeLeftSec, bool $increaseItemsOnEmptyPool): PoolItemWrapperInterface
    {
        $start = hrtime(true);
        $poolItemWrapper = $this->getReservedPoolItemWrapper($timeLeftSec, $increaseItemsOnEmptyPool);

        if (is_null($poolItemWrapper->getItem())) {
            $this->removePoolItemWrapper($poolItemWrapper);

            $recalculatedTimeLeftSec = max(.0001, $timeLeftSec - (hrtime(true) - $start) * 1e-9);

            return $this->getReservedPoolItemWrapperWithExistingItem($recalculatedTimeLeftSec, increaseItemsOnEmptyPool: false);
        }

        return $poolItemWrapper;
    }

    /**
     * Attempts to fill the pool with connections up to the target count.
     * Includes delays to prevent overwhelming the database server.
     *
     * int $targetCount
     */
    public function warmup(): void
    {
        // Ensure target doesn't exceed configured max size
        $target = $this->config->size / 2;
        $createdInWarmup = 0;
        $maxAttempts = $target * 2; // Avoid infinite loop if creation always fails
        $attempts = 0;
        $workerId = getmypid();

        logger()->debug(sprintf('[Worker %s] [Pool %s] Starting warmup. Target: %d, Current Size: %d', $workerId, $this->getName(), $target, $this->itemWrapperCount));

        // Loop until the current size reaches the target, or we exceed max attempts
        while ($this->itemWrapperCount < $target && $attempts < $maxAttempts) {
            $attempts++;
            try {
                // Call increaseItems to create *one* connection wrapper and item
                if ($this->increaseItems()) {
                    $createdInWarmup++;
                    logger()->debug(sprintf('[Worker %s] [Pool %s] Warmup: Increased items. Current size: %d', $workerId, $this->getName(), $this->itemWrapperCount));
                    // Optional small delay to stagger connection attempts across workers/coroutines
                    Coroutine::sleep(0.01); // Sleep 10ms
                } else {
                    // increaseItems returns false if pool is full or push fails, unlikely here but good to log
                    logger()->warning(sprintf('[Worker %s] [Pool %s] Warmup: increaseItems returned false. Current size: %d', $workerId, $this->getName(), $this->itemWrapperCount));
                    Coroutine::sleep(0.1); // Wait longer if push failed
                }
            } catch (Throwable $e) {
                // Log creation errors aggressively during warmup
                logger()->error(sprintf('[Worker %s] [Pool %s] Warmup Error: Failed to create connection (%s). Current size: %d. Error: %s', $workerId, $this->getName(), get_class($e), $this->itemWrapperCount, $e->getMessage()), ['exception' => $e]);
                // Wait before retrying on error to avoid hammering DB if it's down
                Coroutine::sleep(0.5); // Sleep 500ms after an error
            }
        }

        logger()->debug(sprintf('[Worker %s] [Pool %s] Warmup finished. Target: %d, Actual Size: %d, Created: %d, Attempts: %d', $workerId, $this->getName(), $target, $this->itemWrapperCount, $createdInWarmup, $attempts));
    }
}
