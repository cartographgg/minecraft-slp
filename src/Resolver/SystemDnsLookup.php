<?php

declare(strict_types=1);

namespace Cartograph\SLP\Resolver;

/**
 * `DnsLookup` backed by PHP's built-in DNS functions.
 *
 * Calls `dns_get_record` for SRV records and `gethostbyname` for A records. Warnings are suppressed
 * (`@`) so a missing record surfaces as `null` rather than an emitted notice. The resolver
 * interprets `null` as "not found" and falls back accordingly.
 */
final class SystemDnsLookup implements DnsLookup
{
    /**
     * Query SRV records via `dns_get_record`, filtering out malformed entries from the result.
     *
     * @return list<array{target: string, port: int, pri: int}>|null
     */
    public function srv(string $name): ?array
    {
        $records = @dns_get_record($name, DNS_SRV);
        if (! is_array($records) || count($records) === 0) {
            return null;
        }

        $out = [];
        foreach ($records as $record) {
            if (
                ! isset($record['target'], $record['port'], $record['pri'])
                || ! is_string($record['target'])
                || ! is_int($record['port'])
                || ! is_int($record['pri'])
            ) {
                continue;
            }
            $out[] = [
                'target' => $record['target'],
                'port'   => $record['port'],
                'pri'    => $record['pri'],
            ];
        }
        return $out === [] ? null : $out;
    }

    /**
     * Query an A record via `gethostbyname`. PHP returns the input on failure; treat that as "not found".
     */
    public function a(string $name): ?string
    {
        $ip = @gethostbyname($name);
        if ($ip === $name) {
            return null;
        }
        return $ip;
    }
}
