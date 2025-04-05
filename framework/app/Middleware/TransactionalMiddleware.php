<?php
//
//namespace App\Middleware;
//
//use Ody\CQRS\Middleware\Around;
//use Ody\CQRS\Middleware\MethodInvocation;
//
///**
// * Example middleware for database transactions
// */
//class TransactionalMiddleware
//{
//    /**
//     * @var \PDO Database connection
//     */
//    private \PDO $connection;
//
//    /**
//     * Constructor
//     *
//     * @param \PDO $connection
//     */
//    public function __construct(\PDO $connection)
//    {
//        $this->connection = $connection;
//    }
//
//    /**
//     * Wrap command execution in a transaction
//     *
//     * @param MethodInvocation $invocation The method invocation
//     * @return mixed The result of the invocation
//     */
//    #[Around(pointcut: "Ody\\CQRS\\Bus\\CommandBus::executeHandler")]
//    public function transactional(MethodInvocation $invocation): mixed
//    {
//        $this->connection->beginTransaction();
//
//        try {
//            $result = $invocation->proceed();
//            $this->connection->commit();
//            return $result;
//        } catch (\Throwable $exception) {
//            $this->connection->rollBack();
//            throw $exception;
//        }
//    }
//}