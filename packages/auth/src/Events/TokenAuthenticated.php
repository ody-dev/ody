<?php

namespace Ody\Auth\Events;

use Ody\Auth\PersonalAccessToken;

class TokenAuthenticated
{
    /**
     * The personal access token that was authenticated.
     *
     * @var PersonalAccessToken
     */
    public $token;

    /**
     * Create a new event instance.
     *
     * @param  PersonalAccessToken  $token
     * @return void
     */
    public function __construct($token)
    {
        $this->token = $token;
    }
}