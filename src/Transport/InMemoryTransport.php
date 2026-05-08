<?php

declare(strict_types=1);

namespace Cartograph\SLP\Transport;

use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\TransportException;

/**
 * `Transport` for tests, keyed on `host:port`.
 *
 * Tests `register()` a `BufferConnection` for the IP/port the resolver will produce; `connect()`
 * looks up the same key. An unregistered key models a connect-refused failure, mirroring real
 * network behaviour without needing sockets.
 */
final class InMemoryTransport implements Transport
{
    /**
     * Map of `"host:port"` to the canned `Connection` returned for that endpoint.
     *
     * @var array<string, Connection>
     */
    private array $connections = [];

    /**
     * Register `$connection` to be returned the next time `connect($host, $port, ...)` is called.
     *
     * Tests typically register one `BufferConnection` per IP/port the resolver will produce.
     */
    public function register(string $host, int $port, Connection $connection): void
    {
        $this->connections["{$host}:{$port}"] = $connection;
    }

    /**
     * Look up the registered fixture for `$host:$port`. The `$timeout` parameter is ignored.
     *
     * @throws TransportException with `ErrorType::Refused` if no fixture is registered for `host:port`
     */
    public function connect(string $host, int $port, float $timeout = 3.0): Connection
    {
        $key = "{$host}:{$port}";
        if (! isset($this->connections[$key])) {
            throw new TransportException("No InMemoryTransport connection registered for {$key}", ErrorType::Refused);
        }
        return $this->connections[$key];
    }
}
