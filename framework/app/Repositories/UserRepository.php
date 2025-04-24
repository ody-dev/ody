<?php

namespace App\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class UserRepository
{
    public function __construct(
        private Connection $dbConnection
    ) {}

    /**
     * @throws Exception
     */
    public function getAll(): array
    {
        return $this->dbConnection->fetchAllAssociative(
            "SELECT * FROM users"
        );
    }

    /**
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function findById(int $id): array
    {
        // First try by id
        $user = $this->dbConnection->fetchAssociative(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $id]
        );

        if (!$user) {
            return [];
        }

        return $user;
    }

    public function create(array $data): int
    {
        $data = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->dbConnection->insert('users', $data);

        return (int)$this->dbConnection->lastInsertId();
    }
}