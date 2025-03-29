<?php

namespace Ody\CQRS\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware for formatting CQRS responses
 */
class ResponseFormattingMiddleware implements MiddlewareInterface
{
    /**
     * @param ResponseInterface $response Base response
     */
    public function __construct(
        private readonly ResponseInterface $response
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
        // Process the request through the handler first
        $response = $handler->handle($request);

        // Check if we have a query result in the request attributes
        $queryResult = $request->getAttribute('query_result');

        if ($queryResult !== null) {
            // Format the query result as a JSON response
            $jsonResponse = $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

            $responseData = [
                'status' => 'success',
                'data' => $queryResult
            ];

            $jsonResponse->getBody()->write(json_encode($responseData));

            return $jsonResponse;
        }

        // Check if we have a CQRS mapping in the request attributes
        $cqrsMapping = $request->getAttribute('cqrs_mapping');

        if ($cqrsMapping !== null && isset($cqrsMapping['command']) && isset($cqrsMapping['auto_execute']) && $cqrsMapping['auto_execute'] === true) {
            // Command was auto-executed, return a success response
            $jsonResponse = $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

            $responseData = [
                'status' => 'success',
                'message' => 'Command executed successfully'
            ];

            $jsonResponse->getBody()->write(json_encode($responseData));

            return $jsonResponse;
        }

        // If no special handling, return the original response
        return $response;
    }
}