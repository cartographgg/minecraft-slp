<?php

declare(strict_types=1);

namespace Cartograph\SLP\Transport;

use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\Connection\StreamConnection;
use Cartograph\SLP\Exception\TransportException;

/**
 * Default `Transport`: opens real TCP sockets via `stream_socket_client`.
 *
 * The connect timeout is applied at the OS level; the same timeout is then split into whole and
 * fractional seconds and pushed onto the stream as the read timeout, so subsequent `fread`s on
 * the resulting `StreamConnection` honour it.
 */
final class SocketTransport implements Transport
{
    /**
     * Open a TCP connection and return a `StreamConnection` wrapped around it.
     *
     * @throws TransportException with `ErrorType::Refused` for ECONNREFUSED, `ErrorType::Timeout`
     *                            for ETIMEDOUT, and `ErrorType::Other` for any other connect failure
     */
    public function connect(string $host, int $port, float $timeout = 3.0): Connection
    {
        $errno  = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($stream === false) {
            throw TransportException::connect($host, $port, $errno ?? 0, $errstr ?? '');
        }

        $whole = (int) $timeout;
        $micro = (int) (($timeout - $whole) * 1_000_000);
        stream_set_timeout($stream, $whole, $micro);

        return new StreamConnection($stream);
    }
}
