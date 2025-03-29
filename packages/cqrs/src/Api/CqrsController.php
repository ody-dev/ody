<?php

namespace Ody\CQRS\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base controller for CQRS-based API endpoints
 */
abstract class CqrsController
{
    /**
     * @param CqrsApiAdapter $cqrsAdapter
     * @param ResponseInterface $response
     */
    public function __construct(
        protected readonly CqrsApiAdapter    $cqrsAdapter,
        protected readonly ResponseInterface $response
    )
    {
    }

    /**
     * Execute a command and return a success response
     *
     * @param string $commandClass
     * @param ServerRequestInterface $request
     * @param array $additionalData
     * @return ResponseInterface
     */
    protected function command(
        string                 $commandClass,
        ServerRequestInterface $request,
        array                  $additionalData = []
    ): ResponseInterface
    {
        try {
            $this->cqrsAdapter->executeCommand($commandClass, $request, $additionalData);

            return $this->success([
                'status' => 'success',
                'message' => 'Command executed successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return $this->error([
                'status' => 'error',
                'message' => 'An error occurred while processing the command'
            ]);
        }
    }

    /**
     * Return a success response
     *
     * @param array $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function success(array $data, int $status = 200): ResponseInterface
    {
        return $this->jsonResponse($data, $status);
    }

    /**
     * Create a JSON response
     *
     * @param array $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function jsonResponse(array $data, int $status): ResponseInterface
    {
        $response = $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);

        $response->getBody()->write(json_encode($data));

        return $response;
    }

    /**
     * Return a bad request response
     *
     * @param array $data
     * @return ResponseInterface
     */
    protected function badRequest(array $data): ResponseInterface
    {
        return $this->jsonResponse($data, 400);
    }

    /**
     * Return an error response
     *
     * @param array $data
     * @param int $status
     * @return ResponseInterface
     */
    protected function error(array $data, int $status = 500): ResponseInterface
    {
        return $this->jsonResponse($data, $status);
    }

    /**
     * Execute a query and return the result
     *
     * @param string $queryClass
     * @param ServerRequestInterface $request
     * @param array $additionalData
     * @return ResponseInterface
     */
    protected function query(
        string                 $queryClass,
        ServerRequestInterface $request,
        array                  $additionalData = []
    ): ResponseInterface
    {
        try {
            $result = $this->cqrsAdapter->executeQuery($queryClass, $request, $additionalData);

            return $this->success([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            return $this->error([
                'status' => 'error',
                'message' => 'An error occurred while processing the query'
            ]);
        }
    }
}