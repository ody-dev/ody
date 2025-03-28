<?php

namespace Ody\CQRS\Message;

use JsonSerializable;

abstract class Command implements JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        $data = get_object_vars($this);
        $data['__class'] = get_class($this); // Add class information
        return $data;
    }

    public static function fromArray(array $data): self
    {
        $class = $data['__class'] ?? static::class;
        unset($data['__class']);

        return new $class(...$data);
    }
}