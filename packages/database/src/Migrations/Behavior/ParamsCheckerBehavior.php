<?php

declare(strict_types=1);

namespace Ody\DB\Migrations\Behavior;

use Ody\DB\Migrations\Exception\InvalidArgumentValueException;

trait ParamsCheckerBehavior
{
    /**
     * @param string $valueToCheck
     * @param string[] $availableValues
     * @param string $message
     * @throws InvalidArgumentValueException
     */
    protected function inArray(string $valueToCheck, array $availableValues, string $message): void
    {
        if (!in_array($valueToCheck, $availableValues, true)) {
            throw new InvalidArgumentValueException($message);
        }
    }
}
