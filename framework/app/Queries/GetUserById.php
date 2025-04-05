<?php

namespace App\Queries;

class GetUserById
{

    /**
     * @param mixed $int
     */
    public function __construct(private int $id)
    {
        logger()->debug('Get user by id ' . $this->id);
    }

    public function getId(): int
    {
        return $this->id;
    }
}