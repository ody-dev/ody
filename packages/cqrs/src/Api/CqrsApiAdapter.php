<?php

namespace Ody\CQRS\Api;

use Ody\CQRS\Interfaces\CommandBus;
use Ody\CQRS\Interfaces\QueryBus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base adapter for working with CQRS in API controllers
 */
class CqrsApiAdapter
{
    /**
     * @param CommandBus $commandBus
     * @param QueryBus $queryBus
     */
    public function __construct(
        protected readonly CommandBus $commandBus,
        protected readonly QueryBus   $queryBus
    )
    {
    }

    /**
     * Execute a command from an API request
     *
     * @param string $commandClass Fully qualified command class name
     * @param ServerRequestInterface $request The HTTP request
     * @param array $additionalData Any additional data to include in the command
     * @return void
     */
    public function executeCommand(
        string                 $commandClass,
        ServerRequestInterface $request,
        array                  $additionalData = []
    ): void
    {
        // Extract data from request
        $body = $this->getRequestData($request);
        $routeParams = $request->getAttribute('routeParams', []);
        $queryParams = $request->getQueryParams();

        // Merge all data sources
        $data = array_merge($queryParams, $routeParams, $body, $additionalData);

        // Create and dispatch command
        $command = $this->createCommand($commandClass, $data);
        $this->commandBus->dispatch($command);
    }

    /**
     * Get the request data from various content types
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function getRequestData(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            return (array)json_decode((string)$request->getBody(), true) ?? [];
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            return $request->getParsedBody() ?? [];
        }

        if (strpos($contentType, 'multipart/form-data') !== false) {
            return array_merge(
                $request->getParsedBody() ?? [],
                $request->getUploadedFiles() ?? []
            );
        }

        return [];
    }

    /**
     * Create a command instance from data
     *
     * @param string $commandClass
     * @param array $data
     * @return object
     */
    protected function createCommand(string $commandClass, array $data): object
    {
        return $this->createMessage($commandClass, $data);
    }

    /**
     * Create a message (command or query) instance from data
     *
     * @param string $messageClass
     * @param array $data
     * @return object
     */
    protected function createMessage(string $messageClass, array $data): object
    {
        // Use reflection to create the message with the appropriate constructor parameters
        $reflection = new \ReflectionClass($messageClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // No constructor, create the instance and set public properties
            $instance = $reflection->newInstance();
            foreach ($data as $key => $value) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $value;
                }
            }
            return $instance;
        }

        // Get constructor parameters
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            if (array_key_exists($paramName, $data)) {
                $params[] = $data[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Missing required parameter: $paramName");
            }
        }

        // Create the instance with constructor parameters
        return $reflection->newInstanceArgs($params);
    }

    /**
     * Execute a query from an API request
     *
     * @param string $queryClass Fully qualified query class name
     * @param ServerRequestInterface $request The HTTP request
     * @param array $additionalData Any additional data to include in the query
     * @return mixed The query result
     */
    public function executeQuery(
        string                 $queryClass,
        ServerRequestInterface $request,
        array                  $additionalData = []
    ): mixed
    {
        // Extract data from request
        $body = $this->getRequestData($request);
        $routeParams = $request->getAttribute('routeParams', []);
        $queryParams = $request->getQueryParams();

        // Merge all data sources
        $data = array_merge($queryParams, $routeParams, $body, $additionalData);

        // Create and dispatch query
        $query = $this->createQuery($queryClass, $data);
        return $this->queryBus->dispatch($query);
    }

    /**
     * Create a query instance from data
     *
     * @param string $queryClass
     * @param array $data
     * @return object
     */
    protected function createQuery(string $queryClass, array $data): object
    {
        return $this->createMessage($queryClass, $data);
    }
}