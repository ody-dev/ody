<?php

namespace Ody\Auth;

class Identity implements IdentityInterface
{
    /**
     * @var mixed
     */
    private mixed $id;

    /**
     * @var array
     */
    private array $roles;

    /**
     * @var array
     */
    private array $details;

    /**
     * Identity constructor.
     *
     * @param mixed $id User identifier
     * @param array $roles User roles
     * @param array $details Additional user details
     */
    public function __construct(mixed $id, array $roles = [], array $details = [])
    {
        $this->id = $id;
        $this->roles = $roles;
        $this->details = $details;
    }

    /**
     * Get user identifier
     *
     * @return int|string
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Get user roles
     *
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Get specific user detail by key
     *
     * @param string $name The detail key
     * @param mixed|null $default Default value if not found
     * @return mixed
     */
    public function getDetail(string $name, mixed $default = null): mixed
    {
        return $this->details[$name] ?? $default;
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Convert identity to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(
            ['id' => $this->id, 'roles' => $this->roles],
            $this->details
        );
    }

    /**
     * Get the value of a property
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->details[$name] ?? null;
    }

    /**
     * Check if a property exists
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->details[$name]);
    }
}