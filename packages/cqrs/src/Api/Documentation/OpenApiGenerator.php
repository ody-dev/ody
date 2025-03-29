<?php

namespace Ody\CQRS\Api\Documentation;

use Ody\CQRS\Handler\Registry\CommandHandlerRegistry;
use Ody\CQRS\Handler\Registry\QueryHandlerRegistry;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Generates OpenAPI documentation from CQRS commands and queries
 */
class OpenApiGenerator
{
    /**
     * Attribute class for API endpoint documentation
     */
    const API_ENDPOINT_ATTRIBUTE = 'Ody\CQRS\Api\Documentation\ApiEndpoint';

    /**
     * @param CommandHandlerRegistry $commandRegistry
     * @param QueryHandlerRegistry $queryRegistry
     */
    public function __construct(
        private readonly CommandHandlerRegistry $commandRegistry,
        private readonly QueryHandlerRegistry   $queryRegistry
    )
    {
    }

    /**
     * Generate OpenAPI documentation as JSON
     *
     * @param string $title API title
     * @param string $version API version
     * @param string $description API description
     * @return string OpenAPI specification as JSON
     */
    public function generateJson(
        string $title = 'API Documentation',
        string $version = '1.0.0',
        string $description = 'API Documentation generated from CQRS commands and queries'
    ): string
    {
        return json_encode($this->generate($title, $version, $description), JSON_PRETTY_PRINT);
    }

    /**
     * Generate OpenAPI documentation
     *
     * @param string $title API title
     * @param string $version API version
     * @param string $description API description
     * @return array OpenAPI specification
     */
    public function generate(
        string $title = 'API Documentation',
        string $version = '1.0.0',
        string $description = 'API Documentation generated from CQRS commands and queries'
    ): array
    {
        $openApi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $title,
                'version' => $version,
                'description' => $description
            ],
            'paths' => [],
            'components' => [
                'schemas' => [],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ]
        ];

        // Process all commands
        foreach ($this->commandRegistry->getHandlers() as $commandClass => $handlerInfo) {
            $this->processCommand($commandClass, $openApi);
        }

        // Process all queries
        foreach ($this->queryRegistry->getHandlers() as $queryClass => $handlerInfo) {
            $this->processQuery($queryClass, $openApi);
        }

        return $openApi;
    }

    /**
     * Process a command class and add it to the OpenAPI specification
     *
     * @param string $commandClass
     * @param array $openApi
     * @return void
     */
    private function processCommand(string $commandClass, array &$openApi): void
    {
        $reflection = new ReflectionClass($commandClass);

        // Skip if the command doesn't have the ApiEndpoint attribute
        $attributes = $reflection->getAttributes(self::API_ENDPOINT_ATTRIBUTE);
        if (empty($attributes)) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $path = $attribute->path;
        $method = strtolower($attribute->method);
        $summary = $attribute->summary;
        $description = $attribute->description;
        $tags = $attribute->tags;
        $security = $attribute->security;

        // Generate request schema
        $requestSchema = $this->generateSchema($reflection);
        $schemaName = $this->getSchemaName($commandClass);
        $openApi['components']['schemas'][$schemaName] = $requestSchema;

        // Add path
        if (!isset($openApi['paths'][$path])) {
            $openApi['paths'][$path] = [];
        }

        $openApi['paths'][$path][$method] = [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => "#/components/schemas/$schemaName"
                        ]
                    ]
                ]
            ],
            'responses' => [
                '200' => [
                    'description' => 'Command executed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'success'
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'example' => 'Command executed successfully'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '400' => [
                    'description' => 'Bad request',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'error'
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'example' => 'Invalid request parameters'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '500' => [
                    'description' => 'Server error',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'error'
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'example' => 'An error occurred while processing the command'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Add security if needed
        if (!empty($security)) {
            $openApi['paths'][$path][$method]['security'] = $security;
        }
    }

    /**
     * Generate OpenAPI schema from a class
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    private function generateSchema(ReflectionClass $reflection): array
    {
        $properties = [];
        $required = [];

        // Try to get properties from constructor parameters first
        $constructor = $reflection->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $properties[$param->getName()] = $this->getParameterSchema($param);

                if (!$param->isOptional()) {
                    $required[] = $param->getName();
                }
            }
        }

        // If no constructor or no parameters, use public properties
        if (empty($properties)) {
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $properties[$property->getName()] = $this->getPropertySchema($property);

                // Check if property has a default value
                $defaultValue = $property->getDefaultValue();
                if (!$property->hasDefaultValue()) {
                    $required[] = $property->getName();
                }
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Get schema for a parameter
     *
     * @param ReflectionParameter $parameter
     * @return array
     */
    private function getParameterSchema(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if ($type === null) {
            return ['type' => 'string'];
        }

        $typeName = $type->getName();

        switch ($typeName) {
            case 'int':
                return ['type' => 'integer'];
            case 'float':
                return ['type' => 'number'];
            case 'bool':
                return ['type' => 'boolean'];
            case 'array':
                return ['type' => 'array', 'items' => ['type' => 'string']];
            case 'string':
                return ['type' => 'string'];
            default:
                // For complex types, use the class name
                if (class_exists($typeName)) {
                    $reflection = new ReflectionClass($typeName);
                    $schema = $this->generateSchema($reflection);
                    return $schema;
                }
                return ['type' => 'object'];
        }
    }

    /**
     * Get schema for a property
     *
     * @param ReflectionProperty $property
     * @return array
     */
    private function getPropertySchema(ReflectionProperty $property): array
    {
        $type = $property->getType();

        if ($type === null) {
            return ['type' => 'string'];
        }

        $typeName = $type->getName();

        switch ($typeName) {
            case 'int':
                return ['type' => 'integer'];
            case 'float':
                return ['type' => 'number'];
            case 'bool':
                return ['type' => 'boolean'];
            case 'array':
                return ['type' => 'array', 'items' => ['type' => 'string']];
            case 'string':
                return ['type' => 'string'];
            default:
                // For complex types, use the class name
                if (class_exists($typeName)) {
                    $reflection = new ReflectionClass($typeName);
                    $schema = $this->generateSchema($reflection);
                    return $schema;
                }
                return ['type' => 'object'];
        }
    }

    /**
     * Get the schema name from a class name
     *
     * @param string $className
     * @return string
     */
    private function getSchemaName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Process a query class and add it to the OpenAPI specification
     *
     * @param string $queryClass
     * @param array $openApi
     * @return void
     */
    private function processQuery(string $queryClass, array &$openApi): void
    {
        $reflection = new ReflectionClass($queryClass);

        // Skip if the query doesn't have the ApiEndpoint attribute
        $attributes = $reflection->getAttributes(self::API_ENDPOINT_ATTRIBUTE);
        if (empty($attributes)) {
            return;
        }

        $attribute = $attributes[0]->newInstance();
        $path = $attribute->path;
        $method = strtolower($attribute->method);
        $summary = $attribute->summary;
        $description = $attribute->description;
        $tags = $attribute->tags;
        $security = $attribute->security;
        $responseSchema = $attribute->responseSchema;

        // Generate parameter schema
        $parameters = [];
        $querySchema = null;

        if ($method === 'get') {
            // For GET requests, use query parameters
            $parameters = $this->generateQueryParameters($reflection);
        } else {
            // For other methods, use request body
            $querySchema = $this->generateSchema($reflection);
            $schemaName = $this->getSchemaName($queryClass);
            $openApi['components']['schemas'][$schemaName] = $querySchema;
        }

        // Add response schema if provided
        if ($responseSchema) {
            $responseSchemaName = $this->getSchemaName($responseSchema);
            $openApi['components']['schemas'][$responseSchemaName] = $this->generateResponseSchema($responseSchema);
        }

        // Add path
        if (!isset($openApi['paths'][$path])) {
            $openApi['paths'][$path] = [];
        }

        $openApi['paths'][$path][$method] = [
            'summary' => $summary,
            'description' => $description,
            'tags' => $tags,
            'responses' => [
                '200' => [
                    'description' => 'Query executed successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'success'
                                    ],
                                    'data' => $responseSchema ? [
                                        '$ref' => "#/components/schemas/$responseSchemaName"
                                    ] : [
                                        'type' => 'object',
                                        'description' => 'Query result'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '400' => [
                    'description' => 'Bad request',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'error'
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'example' => 'Invalid request parameters'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '500' => [
                    'description' => 'Server error',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => [
                                        'type' => 'string',
                                        'example' => 'error'
                                    ],
                                    'message' => [
                                        'type' => 'string',
                                        'example' => 'An error occurred while processing the query'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Add parameters for GET requests
        if ($method === 'get' && !empty($parameters)) {
            $openApi['paths'][$path][$method]['parameters'] = $parameters;
        } // Add request body for other methods
        elseif ($method !== 'get' && $querySchema) {
            $schemaName = $this->getSchemaName($queryClass);
            $openApi['paths'][$path][$method]['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => "#/components/schemas/$schemaName"
                        ]
                    ]
                ]
            ];
        }

        // Add security if needed
        if (!empty($security)) {
            $openApi['paths'][$path][$method]['security'] = $security;
        }
    }

    /**
     * Generate query parameters for GET requests
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    private function generateQueryParameters(ReflectionClass $reflection): array
    {
        $parameters = [];

        // Try to get properties from constructor parameters first
        $constructor = $reflection->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                $paramSchema = $this->getParameterSchema($param);

                $parameters[] = [
                    'name' => $paramName,
                    'in' => 'query',
                    'required' => !$param->isOptional(),
                    'schema' => $paramSchema
                ];
            }
        }

        // If no constructor or no parameters, use public properties
        if (empty($parameters)) {
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $propertyName = $property->getName();
                $propertySchema = $this->getPropertySchema($property);

                $parameters[] = [
                    'name' => $propertyName,
                    'in' => 'query',
                    'required' => !$property->hasDefaultValue(),
                    'schema' => $propertySchema
                ];
            }
        }

        return $parameters;
    }

    /**
     * Generate schema for response class
     *
     * @param string $className
     * @return array
     */
    private function generateResponseSchema(string $className): array
    {
        $reflection = new ReflectionClass($className);
        return $this->generateSchema($reflection);
    }
}