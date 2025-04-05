<?php

namespace App\Commands;

use Ody\CQRS\Message\Command;

class CreateUserCommand extends Command
{

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     */
    public function __construct(
        private string $name,
        private string $email,
        private string $password
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}