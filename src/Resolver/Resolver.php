<?php

declare(strict_types=1);

namespace Cartograph\SLP\Resolver;

use Cartograph\SLP\Exception\DnsException;

/**
 * Turns a user-supplied address into a concrete `Endpoint`.
 *
 * Inputs are hostnames or IPs, optionally with an explicit port. Implementations may consult SRV/A
 * records or shortcut for literal IPs; the default `DnsResolver` does both.
 */
interface Resolver
{
    /**
     * Resolve `$address` to a concrete IP/port `Endpoint`.
     *
     * @param string   $address hostname or IP literal
     * @param int|null $port    explicit port to use; `null` means "use SRV port if present, else 25565"
     *
     * @throws DnsException if `$address` cannot be resolved to an IP
     */
    public function resolve(string $address, ?int $port = null): Endpoint;
}
