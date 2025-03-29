<?php

namespace Ody\CQRS\Api\Documentation;

use Attribute;

/**
 * Marks a command or query as an API endpoint for documentation purposes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiEndpoint
{
    /**
     * @param string $path API endpoint path
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $summary Short summary of what the endpoint does
     * @param string $description Detailed description of the endpoint
     * @param array $tags OpenAPI tags for grouping endpoints
     * @param array $security Security requirements for the endpoint
     * @param string|null $responseSchema Fully qualified class name of the response schema
     */
    public function __construct(
        public string  $path,
        public string  $method = 'POST',
        public string  $summary = '',
        public string  $description = '',
        public array   $tags = [],
        public array   $security = [['bearerAuth' => []]],
        public ?string $responseSchema = null
    )
    {
    }
}