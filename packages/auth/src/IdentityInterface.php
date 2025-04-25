<?php

namespace Ody\Auth;

interface IdentityInterface
{
    /**
     * Get the unique identifier for the user.
     *
     * @return int|string
     */
    public function getId(): int|string;

    /**
     * Get the roles of the user.
     *
     * @return array
     */
    public function getRoles(): array;

    /**
     * @return array
     */
    public function toArray(): array;
}