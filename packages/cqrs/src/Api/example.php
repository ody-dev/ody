<?php


namespace App\Examples;

use App\Commands\CreateUserCommand;
use App\Queries\GetUserByIdQuery;
use App\Responses\UserResponse;
use Ody\CQRS\Api\CqrsApiAdapter;
use Ody\CQRS\Api\CqrsController;
use Ody\CQRS\Api\Documentation\ApiEndpoint;
use Ody\CQRS\Api\Middleware\RequestMappingConfigFactory;
use Ody\CQRS\Api\Middleware\RequestMappingMiddleware;
use Ody\CQRS\Api\Middleware\ResponseFormattingMiddleware;
use Ody\CQRS\Interfaces\CommandBus;
use Ody\CQRS\Interfaces\QueryBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Example User Command
 */
#[ApiEndpoint(
    path: '/api/users',
    method: 'POST',
    summary: 'Create a new user',
    description: 'Creates a new user with the provided name, email and password',
    tags: ['users']
)]
class CreateUserCommand extends \Ody\CQRS\Message\Command
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    )
    {
    }
}

/**
 * Example User Query
 */
#[ApiEndpoint(
    path: '/api/users/{id}',
    method: 'GET',
    summary: 'Get user by ID',
    description: 'Retrieves a user by their unique identifier',
    tags: ['users'],
    responseSchema: UserResponse::class
)]
class GetUserByIdQuery extends \Ody\CQRS\Message\Query
{
    public function __construct(
        public string $id
    )
    {
    }
}

/**
 * Example Response Class
 */
class UserResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $createdAt
    )
    {
    }
}

/**
 * Example Controller using CQRS
 */
class UserController extends CqrsController
{
    /**
     * Create a new user
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function createUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->command(CreateUserCommand::class, $request);
    }

    /**
     * Get a user by ID
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getUser(ServerRequestInterface $request): ResponseInterface
    {
        return $this->query(GetUserByIdQuery::class, $request);
    }
}

/**
 * Example of setting up middleware for CQRS API
 */
class ApiSetupExample
{
    public function setupMiddleware(
        CommandBus        $commandBus,
        QueryBus          $queryBus,
        ResponseInterface $response
    ): array
    {
        // Create the CQRS adapter
        $cqrsAdapter = new CqrsApiAdapter($commandBus, $queryBus);

        // Create route mapping configuration
        $configFactory = new RequestMappingConfigFactory();
        $config = $configFactory
            ->mapPostToCommand('/api/users', CreateUserCommand::class)
            ->mapGetToQuery('/api/users/{id}', GetUserByIdQuery::class)
            ->getConfig();

        // Create middleware
        $requestMappingMiddleware = new RequestMappingMiddleware($cqrsAdapter, $config);
        $responseFormattingMiddleware = new ResponseFormattingMiddleware($response);

        return [
            $requestMappingMiddleware,
            $responseFormattingMiddleware
        ];
    }

    /**
     * Example of how to generate OpenAPI documentation
     */
    public function generateApiDocs(
        CommandBus $commandBus,
        QueryBus   $queryBus
    ): string
    {
        $generator = new \Ody\CQRS\Api\Documentation\OpenApiGenerator(
            $commandBus->getHandlerRegistry(),
            $queryBus->getHandlerRegistry()
        );

        return $generator->generateJson(
            title: 'My API Documentation',
            version: '1.0.0',
            description: 'API documentation for my application'
        );
    }
}