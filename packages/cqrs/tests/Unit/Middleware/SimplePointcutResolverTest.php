<?php

namespace Ody\CQRS\Tests\Unit\Middleware;

use Ody\CQRS\Middleware\SimplePointcutResolver;
use PHPUnit\Framework\TestCase;

class SimplePointcutResolverTest extends TestCase
{
    private $resolver;

    public function testWildcardPointcut(): void
    {
        $pointcut = '*';
        $targetClass = 'Any\Class\Name';
        $targetMethod = 'anyMethod';

        $this->assertTrue($this->resolver->matches($pointcut, $targetClass, $targetMethod));
    }

    public function testExactClassPointcut(): void
    {
        $pointcut = 'App\Service\UserService';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'anyMethod'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\OtherService', 'anyMethod'));
    }

    public function testNamespaceWildcardPointcut(): void
    {
        $pointcut = 'App\Domain\*';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Domain\User', 'anyMethod'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Domain\Product', 'anyMethod'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\User', 'anyMethod'));
    }

    public function testMethodSpecificationPointcut(): void
    {
        $pointcut = 'App\Service\UserService::createUser';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'createUser'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\UserService', 'updateUser'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\ProductService', 'createUser'));
    }

    public function testWildcardMethodPointcut(): void
    {
        $pointcut = 'App\Service\UserService::*';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'createUser'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'updateUser'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\ProductService', 'createUser'));
    }

    public function testWildcardClassSpecificMethodPointcut(): void
    {
        $pointcut = '*::createUser';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'createUser'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\ProductService', 'createUser'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\UserService', 'updateUser'));
    }

    public function testLogicalOrPointcut(): void
    {
        $pointcut = 'App\Service\UserService || App\Service\ProductService';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'anyMethod'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\ProductService', 'anyMethod'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\OrderService', 'anyMethod'));
    }

    public function testLogicalAndPointcut(): void
    {
        // This is a contrived example since it's hard to make a meaningful AND with classes
        // In practice, this would be more useful with method specifications
        $pointcut = 'App\Service\* && *Service';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'anyMethod'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Domain\UserService', 'anyMethod'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\UserController', 'anyMethod'));
    }

    public function testComplexPointcutCombinations(): void
    {
        $pointcut = 'App\Service\UserService::create* || App\Service\ProductService::update*';

        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'createUser'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\UserService', 'createProduct'));
        $this->assertTrue($this->resolver->matches($pointcut, 'App\Service\ProductService', 'updateProduct'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\UserService', 'updateUser'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\ProductService', 'createProduct'));
        $this->assertFalse($this->resolver->matches($pointcut, 'App\Service\OrderService', 'createOrder'));
    }

    protected function setUp(): void
    {
        $this->resolver = new SimplePointcutResolver();
    }
}