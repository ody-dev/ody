<?php

declare(strict_types=1);

namespace Ody\ConnectionPool;

use LogicException;
use Ody\ConnectionPool\Hooks\ConnectionCheckHook;
use Ody\ConnectionPool\Pool\Hook\PoolItemHookManager;
use Ody\ConnectionPool\Pool\Pool;
use Ody\ConnectionPool\Pool\PoolConfig;
use Ody\ConnectionPool\Pool\PoolInterface;
use Ody\ConnectionPool\Pool\PoolItemFactoryInterface;
use Ody\ConnectionPool\Pool\PoolItemWrapperFactory;
use Ody\ConnectionPool\Pool\PoolItemWrapperInterface;
use Ody\ConnectionPool\Pool\TimerTask\TimerTaskInterface;
use Ody\ConnectionPool\Pool\TimerTask\TimerTaskScheduler;
use Ody\ConnectionPool\Tasks\KeepaliveCheckTimerTask;
use Ody\ConnectionPool\Tasks\LeakDetectionTimerTask;
use Ody\ConnectionPool\Tasks\PoolItemUpdaterTimerTask;
use Ody\ConnectionPool\Tasks\ResizerTimerTask;
use Ody\Logger\StreamLogger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use function array_map;
use function count;
use function substr;
use function uniqid;

/**
 * @template TConnection of object
 */
class ConnectionPoolFactory
{
    protected int $minimumIdle;
    protected bool $autoReturn;
    protected bool $bindToCoroutine;
    protected float $idleTimeoutSec;
    protected float $maxLifetimeSec;
    protected float $borrowingTimeoutSec;
    protected float $returningTimeoutSec;
    protected float $leakDetectionThresholdSec;
    protected float $maxItemReservingForUpdateWaitingTimeSec;

    /** @var list<callable(TConnection): bool> */
    protected array $checkers;

    protected LoggerInterface $logger;

    /** @var list<KeepaliveCheckerInterface<TConnection>> */
    protected array $keepaliveCheckers;

    /**
     * @param  positive-int                           $size
     * @param  PoolItemFactoryInterface<TConnection>  $factory
     */
    public function __construct(
        protected int $size,
        protected PoolItemFactoryInterface $factory,
    ) {
        $this->checkers = [];
        $this->logger = new StreamLogger('php://stdout');
        $this->keepaliveCheckers = [];

        $this->minimumIdle = $this->size;
        $this->autoReturn = true;
        $this->bindToCoroutine = true;
        $this->idleTimeoutSec = 30.0;
        $this->maxLifetimeSec = 300.0;
        $this->borrowingTimeoutSec = .1;
        $this->returningTimeoutSec = .001;
        $this->leakDetectionThresholdSec = 1.0;
        $this->maxItemReservingForUpdateWaitingTimeSec = .01;
    }

    /**
     * @template T of object
     *
     * @param  positive-int                 $size
     * @param  PoolItemFactoryInterface<T>  $factory
     *
     * @return self<T>
     */
    public static function create(int $size, PoolItemFactoryInterface $factory): self
    {
        return new self($size, $factory);
    }

    /**
     * @return self<TConnection>
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setLeakDetectionThresholdSec(float $leakDetectionThresholdSec): self
    {
        $this->leakDetectionThresholdSec = $leakDetectionThresholdSec;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setMaxItemReservingForUpdateWaitingTimeSec(float $maxItemReservingForUpdateWaitingTimeSec): self
    {
        $this->maxItemReservingForUpdateWaitingTimeSec = $maxItemReservingForUpdateWaitingTimeSec;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setAutoReturn(bool $autoReturn): self
    {
        $this->autoReturn = $autoReturn;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setBindToCoroutine(bool $bindToCoroutine): self
    {
        $this->bindToCoroutine = $bindToCoroutine;

        return $this;
    }

    /**
     * @param  positive-int  $minimumIdle
     *
     * @return self<TConnection>
     */
    public function setMinimumIdle(int $minimumIdle): self
    {
        if ($minimumIdle > $this->size) {
            throw new LogicException();
        }

        $this->minimumIdle = $minimumIdle;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setIdleTimeoutSec(float $idleTimeoutSec): self
    {
        $this->idleTimeoutSec = $idleTimeoutSec;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setMaxLifetimeSec(float $maxLifetimeSec): self
    {
        $this->maxLifetimeSec = $maxLifetimeSec;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setBorrowingTimeoutSec(float $borrowingTimeoutSec): self
    {
        $this->borrowingTimeoutSec = $borrowingTimeoutSec;

        return $this;
    }

    /**
     * @return self<TConnection>
     */
    public function setReturningTimeoutSec(float $returningTimeoutSec): self
    {
        $this->returningTimeoutSec = $returningTimeoutSec;

        return $this;
    }

    /**
     * @param  callable(TConnection): bool  $checker
     *
     * @return self<TConnection>
     */
    public function addConnectionChecker(callable $checker): self
    {
        $this->checkers[] = $checker;

        return $this;
    }

    /**
     * @param  KeepaliveCheckerInterface<TConnection>  $keepaliveChecker
     *
     * @return self<TConnection>
     */
    public function addKeepaliveChecker(KeepaliveCheckerInterface $keepaliveChecker): self
    {
        $this->keepaliveCheckers[] = $keepaliveChecker;

        return $this;
    }

    /**
     *
     * @return PoolInterface<TConnection>
     */
    public function instantiate(string $name = ''): PoolInterface
    {
        if ($name == '') {
            $name = $this->generateName();
        }

        $config = new PoolConfig(
            size: $this->size,
            borrowingTimeoutSec: $this->borrowingTimeoutSec,
            returningTimeoutSec: $this->returningTimeoutSec,
            autoReturn: $this->autoReturn,
            bindToCoroutine: $this->bindToCoroutine,
        );

        $timerTaskScheduler = new TimerTaskScheduler([
            new ResizerTimerTask(.1, $this->minimumIdle, $this->idleTimeoutSec, $this->logger),
            new LeakDetectionTimerTask($this->leakDetectionThresholdSec, $this->leakDetectionThresholdSec, $this->logger),
        ]);

        /** @var TimerTaskInterface<PoolItemWrapperInterface<TConnection>> $poolItemUpdaterTimerTask */
        $poolItemUpdaterTimerTask = new PoolItemUpdaterTimerTask(
            intervalSec: $this->maxLifetimeSec / 10,
            maxLifetimeSec: $this->maxLifetimeSec,
            logger: $this->logger,
            maxItemReservingWaitingTimeSec: $this->maxItemReservingForUpdateWaitingTimeSec,
        );

        $poolItemTimerTasks = array_map(
            fn (KeepaliveCheckerInterface $checker) => new KeepaliveCheckTimerTask($this->logger, $checker),
            $this->keepaliveCheckers,
        );

        /** @var TimerTaskScheduler<PoolItemWrapperInterface<TConnection>> $poolItemTimerTaskScheduler */
        $poolItemTimerTaskScheduler = new TimerTaskScheduler([
            $poolItemUpdaterTimerTask,
            ...$poolItemTimerTasks,
        ]);

        $hooks = array_map(fn (callable $checker) => new ConnectionCheckHook($checker, $this->logger), $this->checkers);

        /**
         * @var Pool<TConnection> $pool
         * @psalm-suppress InvalidArgument
         */
        $pool = new Pool(
            name: $name,
            config: $config,
            poolItemWrapperFactory: new PoolItemWrapperFactory(
                factory: $this->factory,
                poolItemTimerTaskScheduler: $poolItemTimerTaskScheduler,
            ),
            logger: $this->logger,
            timerTaskScheduler: $timerTaskScheduler,
            poolItemHookManager: count($hooks) > 0 ? new PoolItemHookManager($hooks) : null,
        );

        return $pool;
    }

    /**
     * @return non-empty-string
     */
    protected function generateName(): string
    {
        $factoryRef = new ReflectionClass($this->factory);

        return $factoryRef->getShortName() . '-' . substr(uniqid(), 0, 8);
    }
}
