<?php
declare(strict_types=1);
namespace Airship\Engine\Networking\HTTP;

use Psr\Http\Message\{
    MessageInterface,
    StreamInterface
};

/**
 * Class Message
 * @package Airship\Engine\Networking\HTTP
 */
class Message implements MessageInterface
{
    /**
     * @var string
     */
    protected $protocolVersion = '';

    /**
     * @var array<mixed, array<mixed, mixed>>
     */
    protected $headers = [];

    /**
     * @var array Map of lowercase header name => original name at registration
     */
    protected $headerNames = [];

    /**
     * @var Stream|null
     */
    protected $body = null;

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return static
     */
    public function withProtocolVersion($version): self
    {
        return (clone $this)
            ->mutate('protocolVersion', $version);
    }

    /**
     * Retrieves all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return array<mixed, array<mixed, mixed>> Returns an associative array of
     *     the message's headers. Each key MUST be a header name, and each value
     *     MUST be an array of strings for that header.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($name): bool
    {
        return !empty($this->headerNames[\strtolower($name)]);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return array<mixed, mixed> Returns an associative array of
     *     the message's headers. Each key MUST be a header name, and each value
     *     MUST be an array of strings for that header.
     */
    public function getHeader($name): array
    {
        $name = \strtolower($name);
        if (empty($this->headerNames[$name])) {
            return [];
        }
        return $this->headers[
            $this->headerNames[$name]
        ];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($name, $value): self
    {
        if (\is_string($value)) {
            $value = [$value];
        }
        $headers = $this->headers;
        $headers[$name] = $value;

        $headerNames = $this->headerNames;
        $headerNames[\strtolower($name)] = $name;

        $cloned = clone $this;
        $cloned
            ->mutate('headerNames', $headerNames)
            ->mutate('headers', $headers);
        return $cloned;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($name, $value): self
    {
        if (\is_string($value)) {
            $value = [$value];
        }

        $headerNames = $this->headerNames;
        $headerNames[\strtolower($name)] = $name;
        $headers = $this->headers;

        if (empty($headers[$name])) {
            $headers[$name] = [];
        }
        $headers[$name] = \array_merge($value, $headers[$name]);

        $cloned = clone $this;
        $cloned
            ->mutate('headerNames', $headerNames)
            ->mutate('headers', $headers);
        return $cloned;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return static
     */
    public function withoutHeader($name): self
    {
        $headerNames = $this->headerNames;
        $headers = $this->headers;
        unset($headers[$name]);
        unset($headerNames[\strtolower($name)]);

        $cloned = clone $this;
        $cloned
            ->mutate('headerNames', $headerNames)
            ->mutate('headers', $headers);
        return $cloned;
    }

    /**
     * Gets the body of the message.
     *
     * @return Stream Returns the body as a stream.
     */
    public function getBody(): Stream
    {
        if (!$this->body instanceof Stream) {
            $this->body = Stream::fromString('');
        }
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return static
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body): self
    {
        if (!$body instanceof Stream) {
            $body = Stream::fromString((string) $body);
        }
        $this->body = $body;
        return $this;
    }

    /**
     * Mutate the current object. For internal use.
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function mutate(string $key, $value): self
    {
        $this->{$key} = $value;
        return $this;
    }

    /**
     * Make sure we're working with a 2D array.
     *
     * @param array $headers
     * @return array<mixed, array<mixed, mixed>>
     */
    protected function preProcessHeaders(array $headers): array
    {
        if (empty($headers)) {
            return [];
        }
        /**
         * @var array<mixed, array<mixed, mixed>>
         */
        $return = [];
        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $return[$key] = $value;
            } else {
                $return[$key] = [$value];
            }
        }
        return $return;
    }
}
