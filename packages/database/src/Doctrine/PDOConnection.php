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
use Doctrine\DBAL\Driver\Exception\IdentityColumnsNotSupported;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Driver\PDO\Statement as PDOStatement;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PDO;
use PDOException;

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

        if ($stmt === false) {
            throw new PDOException("PDO::query() returned false");
        }

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
            throw new PDOException("PDO::exec() returned false");
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(): string|int
    {
        try {
            $value = $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            assert($exception->errorInfo !== null);
            [$sqlState] = $exception->errorInfo;

            // if the PDO driver does not support this capability, PDO::lastInsertId() triggers an IM001 SQLSTATE
            // see https://www.php.net/manual/en/pdo.lastinsertid.php
            if ($sqlState === 'IM001') {
                throw IdentityColumnsNotSupported::new();
            }

            // PDO PGSQL throws a 'lastval is not yet defined in this session' error when no identity value is
            // available, with SQLSTATE 55000 'Object Not In Prerequisite State'
            if ($sqlState === '55000' && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                throw NoIdentityValue::new($exception);
            }

            throw Exception::new($exception);
        }

        // pdo_mysql & pdo_sqlite return '0', pdo_sqlsrv returns '' or false depending on the PHP version
        if ($value === '0' || $value === '' || $value === false) {
            throw NoIdentityValue::new();
        }

        return $value;
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