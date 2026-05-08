<p align="center">
  <img src="cartograph-logo.png" alt="Cartograph Logo" />
</p>

# Minecraft SLP

A pure-PHP library for issuing Minecraft Server List Ping (SLP) requests,
with a managed connection lifecycle and composable packet DTOs for
custom protocol flows.

[![Packagist Version](https://img.shields.io/packagist/v/cartograph/minecraft-slp.svg)](https://packagist.org/packages/cartograph/minecraft-slp)
[![Total Downloads](https://img.shields.io/packagist/dt/cartograph/minecraft-slp.svg)](https://packagist.org/packages/cartograph/minecraft-slp)
[![PHP Version](https://img.shields.io/packagist/php-v/cartograph/minecraft-slp.svg)](https://packagist.org/packages/cartograph/minecraft-slp)
[![License](https://img.shields.io/packagist/l/cartograph/minecraft-slp.svg)](LICENSE.md)

## Features

- **One-call ping**: `Pinger::ping()` runs the standard handshake, status,
  ping/pong, and JSON decode in a single managed exchange
- **Custom flows**: `Pinger::execute()` runs a caller-supplied closure
  inside the same lifecycle (resolve, connect, close, exception
  translation), so verification handshakes and other non-standard flows
  reuse the same plumbing
- **Raw access**: `Pinger::open()` returns the opened `ExecutionContext`
  for callers that need full control over the connection lifetime
- **Sealed result type**: every flow returns `Success<T>` or `Failure`
  (a sealed `PingOutcome`); SLP-protocol errors become `Failure`,
  domain exceptions thrown inside `execute()` propagate to the caller
- **Vanilla and Forge decoding**: the status JSON is parsed into typed
  result objects, with automatic detection of the 1.13+ `forgeData` and
  1.7-1.12 `modinfo` shapes
- **Latency measurement**: optional Ping/Pong round-trip via an
  injectable `Clock` seam, so tests can pin exact latency values
- **SRV-aware resolution**: looks up `_minecraft._tcp.<host>` first,
  falls back to A records, and shortcuts when the input is already an
  IP literal
- **Composable packets**: each packet (Handshake, StatusRequest,
  StatusResponse, Ping, Pong) is a small DTO with `encode()` / `decode()`,
  usable directly without going through `Pinger`
- **PHPStan level max** with full generic annotations on `Connection`,
  `Success`, and `Pinger::execute()`

## Requirements

- PHP 8.5 or newer

No extensions are required; the library uses PHP's built-in
`stream_socket_client` for TCP I/O.

## Installation

Install via Composer:

```bash
composer require cartograph/minecraft-slp
```

## Quick start

Ask a Minecraft server for its status and inspect the result:

```php
use Cartograph\SLP\Failure;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Success;

$pinger  = new Pinger();
$outcome = $pinger->ping('hypixel.net');

if ($outcome instanceof Success) {
    echo $outcome->result->players->online; // e.g. 47892
    echo $outcome->result->version->name;   // e.g. "Requires MC 1.8 / 1.21"
}

if ($outcome instanceof Failure) {
    echo $outcome->type->value;             // e.g. "timeout"
}
```

That's a complete, runnable example. The `Pinger` constructor wires up
sensible production defaults (system DNS, real TCP sockets, monotonic
clock); no fixtures or setup required.

## Concepts

The Server List Ping protocol is the small TCP exchange Minecraft clients
use to populate the multiplayer server list. The flow is:

1. Client opens a TCP connection to the server's port (default 25565)
2. Client sends a **Handshake** packet declaring its protocol version
   and the address it believes it is connecting to
3. Client sends an empty **StatusRequest**
4. Server replies with a **StatusResponse** containing a JSON document
   describing the server (version, players, MOTD, mods, favicon)
5. Client may optionally send a **Ping** with an arbitrary 64-bit
   token; the server echoes it back as a **Pong** so the client can
   compute round-trip latency

This library reads, writes, and orchestrates that exchange. The
high-level entry point is `Pinger`; the low-level building blocks (the
packet DTOs, the `Connection` interface, the `Resolver` and `Transport`
seams) are public so callers can compose custom flows without forking
the library.

Every flow returns a `PingOutcome`: either `Success<T>` carrying the
decoded result, or `Failure` carrying an `ErrorType` and the original
exception. SLP-protocol errors (DNS resolution, connection refused,
timeout, malformed framing, malformed JSON) are caught and converted to
`Failure`. Domain-specific exceptions thrown inside `Pinger::execute()`
propagate to the caller unchanged.

## Usage

### Standard ping

`Pinger::ping()` performs the full Handshake, StatusRequest,
StatusResponse, optional Ping/Pong, and JSON decode in one call.

```php
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Success;

$outcome = new Pinger()->ping('play.example.com');

if ($outcome instanceof Success) {
    $result = $outcome->result;

    echo $result->version->name;               // "1.21.1"
    echo $result->players->online;             //  42
    echo $result->description->plainText;      // "Welcome to Example"
    echo $result->latencyMs ?? 'no measurement'; // 73
}
```

The optional `port`, `protocolVersion`, and `timeout` arguments override
the defaults (25565, `-1`, and 3.0 seconds respectively).

### Custom flows

`Pinger::execute()` runs a caller-supplied closure inside the same
managed lifecycle as `ping()`. The library handles DNS resolution, the
connection lifecycle, and exception translation; the closure runs the
packets it needs and returns whatever the caller wants.

```php
use Cartograph\SLP\ExecutionContext;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Success;

$pinger = new Pinger();

$outcome = $pinger->execute('play.example.com', function (ExecutionContext $ctx) use ($token): string {
    $ctx->connection->send(new Handshake(
        protocolVersion: $ctx->protocolVersion,
        serverAddress:   "verify-{$token}.{$ctx->endpoint->host}",
        serverPort:      $ctx->endpoint->port,
        nextState:       NextState::Status,
    ));
    $ctx->connection->send(new StatusRequest());

    return $ctx->connection->receive(StatusResponse::class)->json;
});

if ($outcome instanceof Success) {
    // $outcome->result is whatever the closure returned (a string here)
}
```

SLP-protocol errors (DNS failure, connect refused, timeout, malformed
JSON, unexpected packet) become `Failure`. Domain exceptions thrown
inside the closure propagate to the caller, and the connection is
closed either way.

### Raw connection access

`Pinger::open()` returns the opened `ExecutionContext` and hands the
connection lifetime to the caller. Useful for long-lived inspection or
multiple non-standard exchanges over a single connection.

```php
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Pinger;

$ctx = new Pinger()->open('play.example.com');
try {
    $ctx->connection->send(new Handshake(-1, $ctx->endpoint->host, $ctx->endpoint->port, NextState::Status));
    $ctx->connection->send(new StatusRequest());
    $status = $ctx->connection->receive(StatusResponse::class);
} finally {
    $ctx->connection->close();
}
```

`open()` throws `DnsException` on resolution failure and
`TransportException` on connect failure rather than wrapping them in a
`Failure` outcome.

### Customising the resolver, transport, decoder, or clock

The `Pinger` constructor takes four collaborators, each an interface
defaulting to a production implementation:

```php
use Cartograph\SLP\JsonStatusDecoder;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Transport\SocketTransport;

$pinger = new Pinger(
    resolver:  new DnsResolver(),       // hostname/IP resolution (uses system DNS)
    transport: new SocketTransport(),   // opens real TCP sockets
    decoder:   new JsonStatusDecoder(), // parses StatusResponse JSON
);
```

Tests substitute `InMemoryTransport`, fake `DnsLookup` implementations,
and a fake `Clock`; production code can swap any of the four for custom
behaviour (cached DNS, an alternative socket library, a logging
decoder).

## Class overview

All classes live under the `Cartograph\SLP` namespace. The public surface
splits into entry points, result types, and protocol primitives.

### Entry point

| Class       | Method                | Returns                                |
|-------------|-----------------------|----------------------------------------|
| `Pinger`    | `ping(string, ...)`   | `Success<PingResult\|ForgePingResult>\|Failure` |
| `Pinger`    | `execute(string, Closure, ...)` | `Success<T>\|Failure` (closure return)    |
| `Pinger`    | `open(string, ...)`   | `ExecutionContext` (caller closes)     |

### Result types

| Class                  | Holds                                               |
|------------------------|-----------------------------------------------------|
| `PingOutcome`          | sealed parent of `Success` and `Failure`            |
| `Success<T>`           | `result: T` (templated)                             |
| `Failure`              | `type: ErrorType`, `previous: ?Throwable`           |
| `Result\PingResult`    | version, players, description, favicon, latency, extras |
| `Result\ForgePingResult` | `base: PingResult`, `forgeData: ForgeData`        |
| `Result\Version`       | `name`, `protocol`                                  |
| `Result\Players`       | `online`, `max`, `sample: list<Sample>`             |
| `Result\Sample`        | `name`, `uuid`                                      |
| `Result\Description`   | `raw` (string or component tree), `plainText`       |
| `Result\ForgeData`     | `fmlNetworkVersion`, `mods`, `channels`             |
| `ErrorType`            | enum: `Dns`, `Refused`, `Timeout`, `ProtocolError`, `Other` |

### Protocol primitives

| Class                          | Role                                              |
|--------------------------------|---------------------------------------------------|
| `Packet\Packet`                | interface implemented by every wire-level packet  |
| `Packet\Handshake`             | client → server, first packet of any exchange     |
| `Packet\StatusRequest`         | client → server, empty body                       |
| `Packet\StatusResponse`        | server → client, JSON body                        |
| `Packet\Ping`                  | client → server, latency token                    |
| `Packet\Pong`                  | server → client, echoed token                     |
| `Packet\NextState`             | enum: `Status`, `Login`                           |
| `Connection\Connection`        | interface for framed packet streams               |
| `Connection\StreamConnection`  | implementation backed by a PHP stream resource    |
| `Connection\BufferConnection`  | in-memory implementation for tests                |
| `Resolver\Resolver`            | interface: address → `Endpoint`                   |
| `Resolver\DnsResolver`         | default; SRV with A-record fallback               |
| `Resolver\Endpoint`            | resolved IP + port                                |
| `Transport\Transport`          | interface: open a `Connection` to a host/port     |
| `Transport\SocketTransport`    | real TCP via `stream_socket_client`               |
| `Transport\InMemoryTransport`  | canned `Connection`s for tests                    |
| `StatusDecoder`                | interface: parses StatusResponse JSON into result objects |
| `JsonStatusDecoder`            | default `StatusDecoder` implementation            |
| `Clock`                        | interface: monotonic millisecond clock            |
| `MonotonicClock`               | default `Clock` backed by `hrtime()`              |

## Compatibility

This library targets the **Java Edition** SLP protocol.

- **Minecraft Java 1.7 and newer**: the modern packet-based handshake
  used by every release since 1.7
- **Forge variants**: 1.13+ `forgeData` and 1.7-1.12 `modinfo` blocks
  surface as `ForgePingResult`
- **Resolution**: SRV records under `_minecraft._tcp.<host>` plus
  A records, and a shortcut for IP literals

The legacy 1.6 SLP variant and Bedrock Edition's UDP-based ping protocol
are not supported.

## Contributing

Bug reports, feature requests, and pull requests are welcome at
[github.com/cartographgg/minecraft-slp](https://github.com/cartographgg/minecraft-slp).
See [`CONTRIBUTING.md`](CONTRIBUTING.md) for development setup and the
required checks (tests, static analysis, code style, and mutation
testing). Each check has a Composer script: `composer test`,
`composer static`, `composer style`, and `composer mutation`.

## License

Released under the [MIT License](LICENSE.md). © Cartograph contributors.

---

Maintained as part of [Cartograph](https://cartograph.gg), a Minecraft
server directory and monitoring platform. The library is self-contained
and has no Cartograph-specific behaviour; use it anywhere you need to
issue SLP requests from PHP.
