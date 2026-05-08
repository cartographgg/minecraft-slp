<?php

declare(strict_types=1);

namespace Cartograph\SLP;

use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\Resolver\Endpoint;

/**
 * Per-flow context for `Pinger::execute()` and `Pinger::open()`.
 *
 * `connection` is already opened to `endpoint`. `address` and `port` are the original (pre-resolve)
 * inputs; `protocolVersion` and `timeout` are the call-site arguments, exposed so closures can
 * build accurate Handshake packets without re-deriving them from the surrounding scope.
 */
final readonly class ExecutionContext
{
    /**
     * @param Connection $connection      already opened to `$endpoint`; closed by `Pinger::execute()`
     *                                    after the closure returns or throws
     * @param Endpoint   $endpoint        resolved IP/port the connection is bound to
     * @param string     $address         original (pre-resolve) hostname or IP the caller supplied
     * @param int|null   $port            explicit port the caller supplied, or `null` for default-port semantics
     * @param int        $protocolVersion Minecraft protocol version to advertise in Handshake packets
     * @param float      $timeout         seconds; same value used for connect and per-read timeout
     */
    public function __construct(
        public Connection $connection,
        public Endpoint $endpoint,
        public string $address,
        public ?int $port,
        public int $protocolVersion,
        public float $timeout,
    ) {
    }
}
