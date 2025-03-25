<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace Ody\DB\Doctrine;

use Doctrine\DBAL\Driver\Result;
use PDO;

/**
 * Result implementation for DBAL PDO driver
 */
class PDOResult implements Result
{
    /**
     * @param \PDOStatement $statement
     */
    public function __construct(private readonly \PDOStatement $statement)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNumeric(): false|array
    {
        $row = $this->statement->fetch(PDO::FETCH_NUM);

        if ($row === false) {
            return false;
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssociative(): false|array
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return false;
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne(): mixed
    {
        $value = $this->statement->fetchColumn(0);

        if ($value === false && $this->statement->columnCount() === 0) {
            return false;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllNumeric(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssociative(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchFirstColumn(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function free(): void
    {
        $this->statement->closeCursor();
    }
}