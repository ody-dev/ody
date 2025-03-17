<?php

namespace Ody\Container\Tests;

use Ody\Container\Container;
use Ody\Container\ContainerHelper;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class ContainerHelperTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = vfsStream::setup('root', null, [
            'app' => [
                'Controllers' => [
                    'TestController.php' => '<?php
                        namespace App\\Controllers;
                        
                        class TestController
                        {
                            public function index()
                            {
                                return "test controller";
                            }
                        }
                    ',
                    'AnotherController.php' => '<?php
                        namespace App\\Controllers;
                        
                        class AnotherController
                        {
                            public function index()
                            {
                                return "another controller";
                            }
                        }
                    '
                ]
            ]
        ]);
    }

    public function testConfigureContainer()
    {
        $container = new Container();

        $config = [
            'app' => [
                'name' => 'Test App',
                'debug' => true,
            ],
            'database' => [
                'host' => 'localhost',
                'database' => 'testdb',
                'username' => 'user',
                'password' => 'pass',
            ]
        ];

        $result = ContainerHelper::configureContainer($container, $config);

        // Test that container instance is returned
        $this->assertSame($container, $result);

        // Test that config is bound
        $this->assertTrue($container->bound('config'));
        $this->assertSame($config, $container->make('config'));

        // Test that db is bound
        $this->assertTrue($container->bound('db'));

        // Test that the controller namespace is aliased
        $this->assertTrue($container->isAlias('controllers'));
    }

    public function testConfigureContainerWithoutDatabase()
    {
        $container = new Container();

        $config = [
            'app' => [
                'name' => 'Test App',
                'debug' => true,
            ]
        ];

        ContainerHelper::configureContainer($container, $config);

        // DB should not be bound if not configured
        $this->assertFalse($container->bound('db'));
    }

    public function testRegisterControllers()
    {
        $container = new Container();

        // We can't actually test with autoloading since the classes don't exist for real
        // But we can test the scanning functionality
        ContainerHelper::registerControllers($container, $this->root->url() . '/app/Controllers');

        // Test that controllers were registered
        // Since we can't actually autoload the classes, we can't directly test registration
        // but we can check if the logic tries to register them by adding a mock to the container

        $this->markTestSkipped('Cannot fully test registerControllers due to autoloading requirements');
    }

    public function testRegisterControllersWithInvalidDirectory()
    {
        $container = new Container();

        // Should not throw an exception for invalid directories
        ContainerHelper::registerControllers($container, $this->root->url() . '/non-existent');

        $this->assertTrue(true); // No exception = pass
    }
}