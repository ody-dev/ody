<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PDO;

/**
 * Connection wrapper that implements Doctrine's Connection interface
 */
class PDOConnection implements Connection
{
    public function __construct(private PDO $pdo)
    {
    }

    // Implement all the methods required by Doctrine's Connection interface
    // Most of these can just delegate to the underlying PDO instance
    // ...
    public function prepare(string $sql): Statement
    {
        // TODO: Implement prepare() method.
    }

    public function query(string $sql): Result
    {
        // TODO: Implement query() method.
    }

    public function quote(string $value): string
    {
        // TODO: Implement quote() method.
    }

    public function exec(string $sql): int|string
    {
        // TODO: Implement exec() method.
    }

    public function lastInsertId(): int|string
    {
        // TODO: Implement lastInsertId() method.
    }

    public function beginTransaction(): void
    {
        // TODO: Implement beginTransaction() method.
    }

    public function commit(): void
    {
        // TODO: Implement commit() method.
    }

    public function rollBack(): void
    {
        // TODO: Implement rollBack() method.
    }

    public function getNativeConnection()
    {
        // TODO: Implement getNativeConnection() method.
    }

    public function getServerVersion(): string
    {
        // TODO: Implement getServerVersion() method.
    }
}