<?php

namespace App\Events;


use Ody\CQRS\Message\Event;

class UserWasCreated extends Event
{
    /**
     * @param int $id
     */
    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}