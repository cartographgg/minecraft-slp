<?php

declare(strict_types=1);

namespace Cartograph\SLP\Resolver;

use Cartograph\SLP\Exception\DnsException;

/**
 * Default `Resolver` for SLP addresses.
 *
 * Shortcut for literal IPs (no DNS query). Otherwise tries the Minecraft SRV record
 * `_minecraft._tcp.<address>` first, falls back to an A-record lookup on the input host, and
 * applies the caller's port override only when no SRV target was found; SRV-supplied ports
 * take precedence.
 */
final class DnsResolver implements Resolver
{
    /**
     * @param DnsLookup $dns underlying DNS implementation; defaults to `SystemDnsLookup` (real DNS)
     */
    public function __construct(private readonly DnsLookup $dns = new SystemDnsLookup())
    {
    }

    /**
     * Resolve `$address` per the rules in the class docblock (IP shortcut, then SRV, then A record).
     *
     * @throws DnsException if the hostname has no A record (or the SRV target's hostname has none)
     */
    public function resolve(string $address, ?int $port = null): Endpoint
    {
        if (filter_var($address, FILTER_VALIDATE_IP) !== false) {
            return new Endpoint($address, $port ?? 25565);
        }

        $srv = $this->dns->srv("_minecraft._tcp.{$address}");
        if ($srv !== null) {
            usort($srv, static fn (array $a, array $b): int => $a['pri'] <=> $b['pri']);
            $resolvedHost = rtrim($srv[0]['target'], '.');
            $resolvedPort = $srv[0]['port'];
        } else {
            $resolvedHost = $address;
            $resolvedPort = $port ?? 25565;
        }

        $ip = $this->dns->a($resolvedHost);
        if ($ip === null) {
            throw DnsException::unresolved($address);
        }

        return new Endpoint($ip, $resolvedPort);
    }
}
