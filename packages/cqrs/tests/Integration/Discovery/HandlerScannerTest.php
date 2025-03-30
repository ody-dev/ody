<?php

namespace Ody\CQRS\Tests\Integration\Discovery;

use Ody\CQRS\Discovery\ClassNameResolver;
use Ody\CQRS\Discovery\FileScanner;
use Ody\CQRS\Discovery\HandlerScanner;
use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\EventHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HandlerScannerTest extends TestCase
{
    private $commandRegistry;
    private $queryRegistry;
    private $eventRegistry;
    private $fileScanner;
    private $classNameResolver;
    private $logger;
    private $scanner;
    private $tempDir;

    public function testScanAndRegisterHandlersFromDirectory(): void
    {
        // Create test command class file
        $testCommand = <<<'PHP'
<?php
namespace Ody\CQRS\Tests\Fixtures;

class TestCommand {}
PHP;

        // Create test query class file
        $testQuery = <<<'PHP'
<?php
namespace Ody\CQRS\Tests\Fixtures;

class TestQuery {}
PHP;

        // Create test event class file
        $testEvent = <<<'PHP'
<?php
namespace Ody\CQRS\Tests\Fixtures;

class TestEvent {}
PHP;

        // Create test handler class file with various handler methods
        $testHandler = <<<'PHP'
<?php
namespace Ody\CQRS\Tests\Fixtures;

use Ody\CQRS\Attributes\CommandHandler;
use Ody\CQRS\Attributes\EventHandler;
use Ody\CQRS\Attributes\QueryHandler;
use Ody\CQRS\Tests\Fixtures\TestCommand;
use Ody\CQRS\Tests\Fixtures\TestQuery;
use Ody\CQRS\Tests\Fixtures\TestEvent;

class TestHandler
{
    #[CommandHandler]
    public function handleCommand(TestCommand $command): void
    {
        // Handle command
    }
    
    #[QueryHandler]
    public function handleQuery(TestQuery $query): array
    {
        // Handle query
        return [];
    }
    
    #[EventHandler]
    public function handleEvent(TestEvent $event): void
    {
        // Handle event
    }
}
PHP;

        $commandFile = $this->tempDir . '/TestCommand.php';
        $queryFile = $this->tempDir . '/TestQuery.php';
        $eventFile = $this->tempDir . '/TestEvent.php';
        $handlerFile = $this->tempDir . '/TestHandler.php';

        file_put_contents($commandFile, $testCommand);
        file_put_contents($queryFile, $testQuery);
        file_put_contents($eventFile, $testEvent);
        file_put_contents($handlerFile, $testHandler);

        // Set up mock fileScanner to return our test files
        $this->fileScanner->expects($this->once())
            ->method('scanDirectory')
            ->with($this->tempDir)
            ->willReturn([$handlerFile]);

        // Set up classNameResolver to resolve class names
        $this->classNameResolver->expects($this->once())
            ->method('resolveFromFile')
            ->with($handlerFile)
            ->willReturn('Tests\Fixtures\TestHandler');

        // Run the scanner - this will call registerHandlersInClass internally
        $this->scanner->scanAndRegister([$this->tempDir]);

        // Assert that handlers were registered correctly
        $this->assertTrue($this->commandRegistry->hasHandlerFor('Tests\Fixtures\TestCommand'));
        $this->assertTrue($this->queryRegistry->hasHandlerFor('Tests\Fixtures\TestQuery'));
        $this->assertTrue($this->eventRegistry->hasHandlersFor('Tests\Fixtures\TestEvent'));
    }

    public function testHandlerRegistration(): void
    {
        // Define a test handler class with attributes
        eval('
            namespace Ody\CQRS\Tests\Fixtures;
            
            use Ody\CQRS\Attributes\CommandHandler;
            use Ody\CQRS\Attributes\EventHandler;
            use Ody\CQRS\Attributes\QueryHandler;
            
            class TestCommand {}
            class TestQuery {}
            class TestEvent {}
            
            class TestHandler {
                #[CommandHandler]
                public function handleCommand(TestCommand $command): void {}
                
                #[QueryHandler]
                public function handleQuery(TestQuery $query): array { return []; }
                
                #[EventHandler]
                public function handleEvent(TestEvent $event): void {}
            }
        ');

        // Call the private registerHandlersInClass method
        $reflection = new \ReflectionClass($this->scanner);
        $method = $reflection->getMethod('registerHandlersInClass');
        $method->setAccessible(true);
        $method->invoke($this->scanner, 'Ody\CQRS\Tests\Fixtures\TestHandler');

        // Verify the handlers were registered correctly
        $this->assertTrue($this->commandRegistry->hasHandlerFor('Ody\CQRS\Tests\Fixtures\TestCommand'));
        $this->assertTrue($this->queryRegistry->hasHandlerFor('Ody\CQRS\Tests\Fixtures\TestQuery'));
        $this->assertTrue($this->eventRegistry->hasHandlersFor('Ody\CQRS\Tests\Fixtures\TestEvent'));

        $commandHandler = $this->commandRegistry->getHandlerFor('Ody\CQRS\Tests\Fixtures\TestCommand');
        $queryHandler = $this->queryRegistry->getHandlerFor('Ody\CQRS\Tests\Fixtures\TestQuery');
        $eventHandlers = $this->eventRegistry->getHandlersFor('Ody\CQRS\Tests\Fixtures\TestEvent');

        $this->assertEquals('Ody\CQRS\Tests\Fixtures\TestHandler', $commandHandler['class']);
        $this->assertEquals('handleCommand', $commandHandler['method']);

        $this->assertEquals('Ody\CQRS\Tests\Fixtures\TestHandler', $queryHandler['class']);
        $this->assertEquals('handleQuery', $queryHandler['method']);

        $this->assertCount(1, $eventHandlers);
        $this->assertEquals('Ody\CQRS\Tests\Fixtures\TestHandler', $eventHandlers[0]['class']);
        $this->assertEquals('handleEvent', $eventHandlers[0]['method']);
    }

    public function testErrorHandlingDuringScanning(): void
    {
        // Setup the scanner to throw an exception during class scanning
        $this->classNameResolver->expects($this->once())
            ->method('resolveFromFile')
            ->willReturn('InvalidClass');

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error scanning class InvalidClass'));

        // Set up fileScanner
        $this->fileScanner->expects($this->once())
            ->method('scanDirectory')
            ->willReturn(['some/file.php']);

        // Run the scanner
        $this->scanner->scanAndRegister(['some/directory']);

        // No assertions needed beyond the expected calls above
        $this->addToAssertionCount(1);
    }

    protected function setUp(): void
    {
        $this->commandRegistry = new CommandHandlerRegistry();
        $this->queryRegistry = new QueryHandlerRegistry();
        $this->eventRegistry = new EventHandlerRegistry();
        $this->fileScanner = $this->createMock(FileScanner::class);
        $this->classNameResolver = $this->createMock(ClassNameResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->scanner = new HandlerScanner(
            $this->commandRegistry,
            $this->queryRegistry,
            $this->eventRegistry,
            $this->fileScanner,
            $this->classNameResolver,
            $this->logger
        );

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/handler_scanner_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}