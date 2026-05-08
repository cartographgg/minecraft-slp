<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

/**
 * Client -> server: ask for a `StatusResponse`.
 *
 * Has no payload; `encode()` returns the empty string. The packet ID alone tells the server what
 * to send back.
 */
final readonly class StatusRequest implements Packet
{
    /**
     * Wire ID `0x00` (in the status state).
     */
    public static function packetId(): int
    {
        return 0x00;
    }

    public static function decode(string $payload): static
    {
        return new self();
    }

    public function encode(): string
    {
        return '';
    }
}
