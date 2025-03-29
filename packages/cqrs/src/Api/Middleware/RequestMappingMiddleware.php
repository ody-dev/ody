<?php

namespace Ody\CQRS\Api\Middleware;

use Ody\CQRS\Api\CqrsApiAdapter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for mapping HTTP requests to CQRS commands and queries
 */
class RequestMappingMiddleware implements MiddlewareInterface
{
    /**
     * @param CqrsApiAdapter $cqrsAdapter
     * @param array $routeConfig Configuration for routes to commands/queries mapping
     */
    public function __construct(
        private readonly CqrsApiAdapter $cqrsAdapter,
        private readonly array          $routeConfig = []
    )
    {
    }

    /**
     * Process an incoming server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get the current route path and method
        $path = $request->getUri()->getPath();
        $method = strtolower($request->getMethod());

        // Check if we have a mapping for this route
        if (isset($this->routeConfig[$path][$method])) {
            $config = $this->routeConfig[$path][$method];

            // Store the mapping in the request attributes for later use
            $request = $request->withAttribute('cqrs_mapping', $config);

            // Pre-execute the command or query if configured
            if (isset($config['auto_execute']) && $config['auto_execute'] === true) {
                if (isset($config['command'])) {
                    // Execute command
                    $this->cqrsAdapter->executeCommand(
                        $config['command'],
                        $request,
                        $config['additional_data'] ?? []
                    );
                } elseif (isset($config['query'])) {
                    // Execute query and store result in request
                    $result = $this->cqrsAdapter->executeQuery(
                        $config['query'],
                        $request,
                        $config['additional_data'] ?? []
                    );

                    $request = $request->withAttribute('query_result', $result);
                }
            }
        }

        return $handler->handle($request);
    }
}

/**
 * Factory for creating route configurations for the RequestMappingMiddleware
 */
class RequestMappingConfigFactory
{
    /**
     * @var array
     */
    private array $config = [];

    /**
     * Map a GET route to a query
     *
     * @param string $path Route path
     * @param string $queryClass Fully qualified query class name
     * @param bool $autoExecute Whether to auto-execute the query
     * @param array $additionalData Additional data to include in the query
     * @return self
     */
    public function mapGetToQuery(
        string $path,
        string $queryClass,
        bool   $autoExecute = true,
        array  $additionalData = []
    ): self
    {
        $this->config[$path]['get'] = [
            'query' => $queryClass,
            'auto_execute' => $autoExecute,
            'additional_data' => $additionalData
        ];

        return $this;
    }

    /**
     * Map a POST route to a command
     *
     * @param string $path Route path
     * @param string $commandClass Fully qualified command class name
     * @param bool $autoExecute Whether to auto-execute the command
     * @param array $additionalData Additional data to include in the command
     * @return self
     */
    public function mapPostToCommand(
        string $path,
        string $commandClass,
        bool   $autoExecute = true,
        array  $additionalData = []
    ): self
    {
        $this->config[$path]['post'] = [
            'command' => $commandClass,
            'auto_execute' => $autoExecute,
            'additional_data' => $additionalData
        ];

        return $this;
    }

    /**
     * Map a PUT route to a command
     *
     * @param string $path Route path
     * @param string $commandClass Fully qualified command class name
     * @param bool $autoExecute Whether to auto-execute the command
     * @param array $additionalData Additional data to include in the command
     * @return self
     */
    public function mapPutToCommand(
        string $path,
        string $commandClass,
        bool   $autoExecute = true,
        array  $additionalData = []
    ): self
    {
        $this->config[$path]['put'] = [
            'command' => $commandClass,
            'auto_execute' => $autoExecute,
            'additional_data' => $additionalData
        ];

        return $this;
    }

    /**
     * Map a DELETE route to a command
     *
     * @param string $path Route path
     * @param string $commandClass Fully qualified command class name
     * @param bool $autoExecute Whether to auto-execute the command
     * @param array $additionalData Additional data to include in the command
     * @return self
     */
    public function mapDeleteToCommand(
        string $path,
        string $commandClass,
        bool   $autoExecute = true,
        array  $additionalData = []
    ): self
    {
        $this->config[$path]['delete'] = [
            'command' => $commandClass,
            'auto_execute' => $autoExecute,
            'additional_data' => $additionalData
        ];

        return $this;
    }

    /**
     * Map a POST route to a query
     *
     * @param string $path Route path
     * @param string $queryClass Fully qualified query class name
     * @param bool $autoExecute Whether to auto-execute the query
     * @param array $additionalData Additional data to include in the query
     * @return self
     */
    public function mapPostToQuery(
        string $path,
        string $queryClass,
        bool   $autoExecute = true,
        array  $additionalData = []
    ): self
    {
        $this->config[$path]['post'] = [
            'query' => $queryClass,
            'auto_execute' => $autoExecute,
            'additional_data' => $additionalData
        ];

        return $this;
    }

    /**
     * Create custom mapping
     *
     * @param string $path Route path
     * @param string $method HTTP method (get, post, put, delete, etc.)
     * @param array $config Configuration for the mapping
     * @return self
     */
    public function createMapping(string $path, string $method, array $config): self
    {
        $this->config[$path][strtolower($method)] = $config;

        return $this;
    }

    /**
     * Get the configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}