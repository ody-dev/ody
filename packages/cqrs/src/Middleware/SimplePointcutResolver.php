<?php

namespace Ody\CQRS\Middleware;

class SimplePointcutResolver implements PointcutResolver
{
    /**
     * Check if the pointcut expression matches the target class/method
     *
     * @param string $pointcut The pointcut expression
     * @param string $targetClass The fully qualified class name
     * @param string|null $targetMethod The method name (optional)
     * @return bool
     */
    public function matches(string $pointcut, string $targetClass, ?string $targetMethod = null): bool
    {
        // Handle wildcard expression
        if ($pointcut === '*') {
            return true;
        }

        // Handle logical OR expressions
        if (str_contains($pointcut, '||')) {
            $expressions = array_map('trim', explode('||', $pointcut));
            foreach ($expressions as $expr) {
                if ($this->matches($expr, $targetClass, $targetMethod)) {
                    return true;
                }
            }
            return false;
        }

        // Handle logical AND expressions
        if (str_contains($pointcut, '&&')) {
            $expressions = array_map('trim', explode('&&', $pointcut));
            foreach ($expressions as $expr) {
                if (!$this->matches($expr, $targetClass, $targetMethod)) {
                    return false;
                }
            }
            return true;
        }

        // Handle namespace wildcard (e.g., App\Domain\*)
        if (str_ends_with($pointcut, '\*')) {
            $namespace = substr($pointcut, 0, -2);
            return str_starts_with($targetClass, $namespace);
        }

        // Handle method specification (Class::method)
        if (str_contains($pointcut, '::') && $targetMethod !== null) {
            list($className, $methodName) = explode('::', $pointcut);

            // Check class match
            $classMatches = ($className === '*' || $className === $targetClass);

            // Check method match (can include wildcard)
            $methodMatches = ($methodName === '*' || $methodName === $targetMethod);

            return $classMatches && $methodMatches;
        }

        // Direct class name matching
        return $pointcut === $targetClass;
    }
}