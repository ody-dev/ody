<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Ody\Foundation\Http;

use InvalidArgumentException;
use Laminas\Diactoros\Exception\UnreadableStreamException;
use Laminas\Diactoros\Exception\UnseekableStreamException;
use Laminas\Diactoros\Exception\UntellableStreamException;
use Laminas\Diactoros\Exception\UnwritableStreamException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Stringable;
use Throwable;
use function array_key_exists;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fwrite;
use function get_resource_type;
use function in_array;
use function is_int;
use function is_resource;
use function is_string;
use function sprintf;
use function str_contains;
use function stream_get_contents;
use function stream_get_meta_data;
use const SEEK_SET;

/**
 * Implementation of PSR HTTP streams
 */
class Stream implements StreamInterface, Stringable
{
    /**
     * A list of allowed stream resource types that are allowed to instantiate a Stream
     */
    private const ALLOWED_STREAM_RESOURCE_TYPES = ['stream'];

    /** @var resource|null */
    protected $resource;

    /** @var string|object|resource|null */
    protected $stream;

    /**
     * @param string|object|resource $stream
     * @param string $mode Mode with which to open stream
     * @throws InvalidArgumentException
     */
    public function __construct($stream, string $mode = 'r')
    {
        $this->setStream($stream, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        if (! $this->isReadable()) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (! $this->resource) {
            return;
        }

        $resource = $this->detach();
        fclose($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $resource       = $this->resource;
        $this->resource = null;
        return $resource;
    }

    /**
     * Attach a new stream/resource to the instance.
     *
     * @param string|object|resource $resource
     * @throws InvalidArgumentException For stream identifier that cannot be cast to a resource.
     * @throws InvalidArgumentException For non-resource stream.
     */
    public function attach($resource, string $mode = 'r'): void
    {
        // Close the existing resource if it exists
        if ($this->resource !== null) {
            $this->close();
        }

        $this->setStream($resource, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        if (null === $this->resource) {
            return null;
        }

        $stats = fstat($this->resource);
        if ($stats !== false) {
            return $stats['size'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        if (! $this->resource) {
            throw UntellableStreamException::dueToMissingResource();
        }

        $result = ftell($this->resource);
        if (! is_int($result)) {
            throw UntellableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        if (! $this->resource) {
            return true;
        }

        return feof($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        return $meta['seekable'];
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (! $this->resource) {
            throw UnseekableStreamException::dueToMissingResource();
        }

        if (! $this->isSeekable()) {
            throw UnseekableStreamException::dueToConfiguration();
        }

        $result = fseek($this->resource, $offset, $whence);

        if (0 !== $result) {
            throw UnseekableStreamException::dueToPhpError();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return str_contains($mode, 'x')
            || str_contains($mode, 'w')
            || str_contains($mode, 'c')
            || str_contains($mode, 'a')
            || str_contains($mode, '+');
    }

    /**
     * {@inheritdoc}
     */
    public function write($string): int
    {
        if (! $this->resource) {
            throw UnwritableStreamException::dueToMissingResource();
        }

        if (! $this->isWritable()) {
            throw UnwritableStreamException::dueToConfiguration();
        }

        $result = fwrite($this->resource, $string);

        if (false === $result) {
            throw UnwritableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        if (! $this->resource) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length): string
    {
        if (! $this->resource) {
            throw UnreadableStreamException::dueToMissingResource();
        }

        if (! $this->isReadable()) {
            throw UnreadableStreamException::dueToConfiguration();
        }

        $result = fread($this->resource, $length);

        if (false === $result) {
            throw UnreadableStreamException::dueToPhpError();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        if (! $this->isReadable()) {
            throw UnreadableStreamException::dueToConfiguration();
        }

        $result = stream_get_contents($this->resource);
        if (false === $result) {
            throw UnreadableStreamException::dueToPhpError();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(?string $key = null)
    {
        $metadata = [];
        if (null !== $this->resource) {
            $metadata = stream_get_meta_data($this->resource);
        }

        if (null === $key) {
            return $metadata;
        }

        if (! array_key_exists($key, $metadata)) {
            return null;
        }

        return $metadata[$key];
    }

    /**
     * Set the internal stream resource.
     *
     * @param string|object|resource $stream String stream target or stream resource.
     * @param string $mode Resource mode for stream target.
     * @throws InvalidArgumentException For invalid streams or resources.
     */
    private function setStream($stream, string $mode = 'r'): void
    {
        $error    = null;
        $resource = $stream;

        if (is_string($stream)) {
            try {
                $resource = fopen($stream, $mode);
            } catch (Throwable $error) {
            }

            if (! is_resource($resource)) {
                throw new RuntimeException(
                    sprintf(
                        'Empty or non-existent stream identifier or file path provided: "%s"',
                        $stream,
                    ),
                    0,
                    $error
                );
            }
        }

        if (! $this->isValidStreamResourceType($resource)) {
            throw new InvalidArgumentException(
                'Invalid stream provided; must be a string stream identifier or stream resource'
            );
        }

        if ($stream !== $resource) {
            $this->stream = $stream;
        }

        $this->resource = $resource;
    }

    /**
     * Determine if a resource is one of the resource types allowed to instantiate a Stream
     *
     * @param mixed $resource Stream resource.
     * @psalm-assert-if-true resource $resource
     */
    private function isValidStreamResourceType(mixed $resource): bool
    {
        if (is_resource($resource)) {
            return in_array(get_resource_type($resource), self::ALLOWED_STREAM_RESOURCE_TYPES, true);
        }

        return false;
    }

    /**
     * Ensure the stream is properly initialized in Swoole context
     *
     * @return bool True if stream is valid
     */
    public function ensureStreamValid(): bool
    {
        if (!$this->resource) {
            return false;
        }

        // Check if we're in a Swoole environment
        if (extension_loaded('swoole')) {
            // Get the URI for file-based streams
            $meta = $this->getMetadata();
            $uri = $meta['uri'] ?? null;

            // If it's a file, verify it exists and is readable
            if ($uri && is_string($uri) && file_exists($uri)) {
                // Resource is valid
                return true;
            } elseif ($uri) {
                // Try to reopen the file if it exists but resource is invalid
                try {
                    $mode = $meta['mode'] ?? 'r';
                    $this->resource = fopen($uri, $mode);
                    return is_resource($this->resource);
                } catch (\Throwable $e) {
                    return false;
                }
            }
        }

        // Default: check if resource is still valid
        return is_resource($this->resource);
    }

    /**
     * Destructor - ensures resources are closed when object is destroyed
     */
    public function __destruct()
    {
        $this->close();
    }
}