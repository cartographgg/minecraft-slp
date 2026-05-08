<?php

declare(strict_types=1);

namespace Cartograph\SLP\Resolver;

/**
 * Thin seam over PHP's DNS APIs.
 *
 * Exists so resolvers are testable without hitting real DNS. The system implementation is
 * `SystemDnsLookup`; tests substitute anonymous classes that return canned records.
 */
interface DnsLookup
{
    /**
     * Look up SRV records for `$name`.
     *
     * @return list<array{target: string, port: int, pri: int}>|null `null` for no records
     */
    public function srv(string $name): ?array;

    /**
     * Return the resolved IP for `$name`, or `null` if it does not resolve.
     */
    public function a(string $name): ?string;
}
