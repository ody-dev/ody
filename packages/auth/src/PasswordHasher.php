<?php

namespace Ody\Auth;

class PasswordHasher
{
    /**
     * Default crypt cost factor.
     *
     * @var int
     */
    protected $rounds = 10;

    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @return string
     */
    public function make($value)
    {
        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->rounds,
        ]);
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check($value, $hashedValue)
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @return bool
     */
    public function needsRehash($hashedValue)
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->rounds,
        ]);
    }

    /**
     * Set the default password work factor.
     *
     * @param  int  $rounds
     * @return $this
     */
    public function setRounds($rounds)
    {
        $this->rounds = (int) $rounds;

        return $this;
    }

    /**
     * Create a new hasher instance.
     *
     * @param  array  $options
     * @return static
     */
    public static function create(array $options = [])
    {
        $hash = new static();

        if (isset($options['rounds'])) {
            $hash->setRounds($options['rounds']);
        }

        return $hash;
    }
}