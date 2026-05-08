<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\LongInt;

/**
 * Client -> server: optional latency-measurement packet.
 *
 * Sent after a `StatusResponse`. `payload` is an arbitrary 64-bit token the server must echo back
 * in a matching `Pong`; the client compares the round-trip wall time to derive latency.
 */
final readonly class Ping implements Packet
{
    /**
     * Wire ID `0x01` (in the status state).
     */
    public static function packetId(): int
    {
        return 0x01;
    }

    public static function decode(string $payload): static
    {
        return new self(payload: LongInt::decode(new Buffer($payload)));
    }

    /**
     * @param int $payload arbitrary 64-bit token; the server must echo it back unchanged in `Pong`
     */
    public function __construct(public int $payload)
    {
    }

    public function encode(): string
    {
        return LongInt::encode($this->payload);
    }
}
