<?php

declare(strict_types=1);

namespace Ody\ConnectionPool\ConnectionPool\Hooks;

use Psr\Log\LoggerInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\Hook\PoolItemHook;
use Ody\ConnectionPool\ConnectionPool\Pool\PoolItemWrapperInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\Hook\PoolItemHookInterface;
use Ody\ConnectionPool\ConnectionPool\Pool\Exceptions\PoolItemCreationException;

/**
 * @template TItem of object
 * @implements PoolItemHookInterface<TItem>
 */
readonly class ConnectionCheckHook implements PoolItemHookInterface
{
    /**
     * @param  callable(TItem): bool  $checker
     */
    public function __construct(
        protected mixed $checker,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invoke(PoolItemWrapperInterface $poolItemWrapper): void
    {
        $item = $poolItemWrapper->getItem();
        $checker = $this->checker;

        if (is_null($item) || $checker($item)) {
            return;
        }

        try {
            $poolItemWrapper->recreateItem();
        } catch (PoolItemCreationException $exception) {
            $this->logger->error('Can\'t recreate item: ' . $exception->getMessage(), ['item_id' => $poolItemWrapper->getId()]);
        }
    }

    public function getHook(): PoolItemHook
    {
        return PoolItemHook::BEFORE_BORROW;
    }
}
