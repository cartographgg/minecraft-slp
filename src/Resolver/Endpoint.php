<?php

declare(strict_types=1);

namespace Cartograph\SLP\Resolver;

/**
 * Resolved network destination.
 *
 * `host` is always an IP address, not a hostname; by the time an `Endpoint` exists, name
 * resolution has happened. `port` is the TCP port the SLP socket should connect to.
 */
final readonly class Endpoint
{
    /**
     * @param string $host resolved IP address (not a hostname)
     * @param int    $port TCP port the SLP socket should connect to
     */
    public function __construct(
        public string $host,
        public int $port,
    ) {
    }
}
