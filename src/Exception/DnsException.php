<?php

declare(strict_types=1);

namespace Cartograph\SLP\Exception;

/**
 * Raised when an address cannot be resolved to an IP.
 *
 * Surfaced from `Resolver::resolve()`. Maps to `ErrorType::Dns` once caught and converted to
 * `Failure` by `Pinger::execute()`.
 */
final class DnsException extends MinecraftSlpException
{
    /**
     * Build the canonical "address could not be resolved" exception for `$address`.
     */
    public static function unresolved(string $address): self
    {
        return new self("Could not resolve address: {$address}");
    }
}
