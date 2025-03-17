<?php

namespace Ody\Auth\Exceptions;

use Exception;

class MissingAbilityException extends Exception
{
    /**
     * The abilities that the user does not have.
     *
     * @var array
     */
    protected $abilities;

    /**
     * Create a new missing ability exception.
     *
     * @param  array|string  $abilities
     * @param  string  $message
     * @return void
     */
    public function __construct($abilities = [], $message = 'Invalid ability provided.')
    {
        parent::__construct($message);

        $this->abilities = is_array($abilities) ? $abilities : [$abilities];
    }

    /**
     * Get the abilities that the user does not have.
     *
     * @return array
     */
    public function abilities()
    {
        return $this->abilities;
    }
}