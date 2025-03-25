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
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PDO;

/**
 * Connection wrapper that implements Doctrine's Connection interface
 */
readonly class PDOConnection implements Connection
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql): Statement
    {
        return new PDOStatement($this->pdo->prepare($sql));
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql): Result
    {
        $stmt = $this->pdo->query($sql);

        return new PDOResult($stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $sql): int|string
    {
        $result = $this->pdo->exec($sql);

        if ($result === false) {
            throw new \PDOException("PDO::exec() returned false");
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): int|string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * {@inheritdoc}
     */
    public function getNativeConnection()
    {
        return $this->pdo;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
}