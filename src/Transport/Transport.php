<?php

declare(strict_types=1);

namespace Cartograph\SLP\Transport;

use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\Exception\TransportException;

/**
 * Establishes a `Connection` to a host/port.
 *
 * The seam between Pinger and the network: `SocketTransport` opens real TCP sockets, while
 * `InMemoryTransport` returns canned `Connection`s for tests without touching the network.
 */
interface Transport
{
    /**
     * Open a `Connection` to `$host:$port`.
     *
     * @param string $host    resolved IP or hostname (implementations may handle either)
     * @param int    $port    TCP port
     * @param float  $timeout connect timeout in seconds; implementations should honour this for both
     *                        the connect attempt and any per-read timeouts they configure
     *
     * @throws TransportException if the connection cannot be opened (refused, timeout, host
     *                            unreachable, or, for `InMemoryTransport`, no fixture registered)
     */
    public function connect(string $host, int $port, float $timeout = 3.0): Connection;
}
