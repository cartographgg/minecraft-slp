<?php

declare(strict_types=1);

namespace Cartograph\SLP;

use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\Exception\DnsException;
use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\Ping;
use Cartograph\SLP\Packet\Pong;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Resolver\Resolver;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Transport\SocketTransport;
use Cartograph\SLP\Transport\Transport;
use Closure;
use Throwable;

/**
 * Entry point for issuing SLP requests against a Minecraft server.
 *
 * Wires together a `Resolver`, `Transport`, `StatusDecoder`, and `Clock`. `ping()` runs the
 * standard status-and-latency exchange; `execute()` runs a caller-supplied closure inside the
 * same managed lifecycle (resolve, connect, close, plus exception translation); `open()`
 * exposes the raw `ExecutionContext` for callers that want full control over the connection
 * lifetime.
 */
final class Pinger
{
    /**
     * All four parameters default to production implementations.
     *
     * @param Resolver      $resolver  hostname/IP resolution; defaults to `DnsResolver` (system DNS)
     * @param Transport     $transport opens TCP connections; defaults to `SocketTransport` (real sockets)
     * @param StatusDecoder $decoder   parses StatusResponse JSON into `PingResult`/`ForgePingResult`
     * @param Clock         $clock     monotonic clock used by `measurePing()`; tests inject a fake
     */
    public function __construct(
        private readonly Resolver $resolver = new DnsResolver(),
        private readonly Transport $transport = new SocketTransport(),
        private readonly StatusDecoder $decoder = new JsonStatusDecoder(),
        private readonly Clock $clock = new MonotonicClock(),
    ) {
    }

    /**
     * Run a custom flow against `$address`. Pinger handles resolve + connect + close;
     * the closure runs the packet exchange and returns whatever it likes.
     *
     * SLP-protocol errors (DnsException during resolve, TransportException during connect
     * or anywhere in the closure, ProtocolException, MalformedJsonException) are caught
     * and converted to `Failure`. Domain-specific exceptions thrown by the closure
     * propagate to the caller. The connection is closed when the closure returns or throws.
     *
     * @template T
     *
     * @param Closure(ExecutionContext): T $flow
     *
     * @return Success<T>|Failure
     */
    public function execute(
        string $address,
        Closure $flow,
        ?int $port = null,
        int $protocolVersion = -1,
        float $timeout = 3.0,
    ): PingOutcome {
        try {
            $endpoint = $this->resolver->resolve($address, $port);
        } catch (DnsException $e) {
            return new Failure(ErrorType::Dns, $e);
        }

        try {
            $connection = $this->transport->connect($endpoint->host, $endpoint->port, $timeout);
        } catch (TransportException $e) {
            return new Failure($e->type, $e);
        }

        $context = new ExecutionContext(
            connection: $connection,
            endpoint: $endpoint,
            address: $address,
            port: $port,
            protocolVersion: $protocolVersion,
            timeout: $timeout,
        );

        try {
            return new Success($flow($context));
        } catch (TransportException $e) {
            return new Failure($e->type, $e);
        } catch (ProtocolException|MalformedJsonException $e) {
            return new Failure(ErrorType::ProtocolError, $e);
        } finally {
            $connection->close();
        }
    }

    /**
     * Open a connection and return the ExecutionContext for raw use.
     *
     * The caller MUST close `$context->connection` (try/finally is the conventional pattern).
     * Errors during resolve or connect throw rather than being wrapped in PingOutcome.
     *
     * @throws DnsException       if address resolution fails
     * @throws TransportException if the connection cannot be established
     */
    public function open(
        string $address,
        ?int $port = null,
        int $protocolVersion = -1,
        float $timeout = 3.0,
    ): ExecutionContext {
        $endpoint   = $this->resolver->resolve($address, $port);
        $connection = $this->transport->connect($endpoint->host, $endpoint->port, $timeout);

        return new ExecutionContext(
            connection: $connection,
            endpoint: $endpoint,
            address: $address,
            port: $port,
            protocolVersion: $protocolVersion,
            timeout: $timeout,
        );
    }

    /**
     * Run the standard SLP status exchange against `$address`.
     *
     * Performs Handshake → StatusRequest → StatusResponse and (best-effort) Ping/Pong for latency,
     * then decodes the JSON. SLP-protocol errors are caught and returned as `Failure`; this method
     * does not throw under any documented condition.
     *
     * @return Success<PingResult|ForgePingResult>|Failure
     */
    public function ping(
        string $address,
        ?int $port = null,
        int $protocolVersion = -1,
        float $timeout = 3.0,
    ): PingOutcome {
        return $this->execute(
            $address,
            fn (ExecutionContext $ctx): PingResult|ForgePingResult => $this->standardPing($ctx),
            port: $port,
            protocolVersion: $protocolVersion,
            timeout: $timeout,
        );
    }

    /**
     * The packet exchange `ping()` runs inside `execute()`'s managed lifecycle.
     *
     * Sends Handshake plus StatusRequest, reads the StatusResponse, then attempts a Ping/Pong for
     * latency, and decodes the JSON. Exceptions propagate so `execute()`'s catch blocks can convert
     * them into `Failure`.
     *
     * @throws TransportException     if the underlying stream fails or times out mid-exchange
     * @throws ProtocolException      if framing is malformed or a packet ID is unexpected
     * @throws MalformedJsonException if the StatusResponse JSON does not parse
     */
    private function standardPing(ExecutionContext $ctx): PingResult|ForgePingResult
    {
        $ctx->connection->send(new Handshake(
            protocolVersion: $ctx->protocolVersion,
            serverAddress: $ctx->endpoint->host,
            serverPort: $ctx->endpoint->port,
            nextState: NextState::Status,
        ));
        $ctx->connection->send(new StatusRequest());
        $status    = $ctx->connection->receive(StatusResponse::class);
        $latencyMs = $this->measurePing($ctx->connection);

        return $this->decoder->decode($status->json, $latencyMs);
    }

    /**
     * Best-effort latency probe via the Ping/Pong packets.
     *
     * Returns the round-trip in milliseconds, or `null` if the exchange fails or the server echoes
     * a different payload back. Swallows all `Throwable`s deliberately, since latency is
     * opportunistic and a failure here must not abort the surrounding flow.
     */
    private function measurePing(Connection $conn): ?int
    {
        $start = $this->clock->monotonicMs();
        try {
            $conn->send(new Ping($start));
            $pong = $conn->receive(Pong::class);
            $end  = $this->clock->monotonicMs();
            return $pong->payload === $start ? $end - $start : null;
        } catch (Throwable) {
            return null;
        }
    }
}
