<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\LongInt;

/**
 * Server -> client: echo of the matching `Ping`.
 *
 * If the echoed `payload` disagrees with the one the client sent, latency is reported as `null`
 * rather than a wrong number; see `Pinger::measurePing()`.
 */
final readonly class Pong implements Packet
{
    /**
     * Wire ID `0x01` (in the status state). Same numeric value as `Ping`; direction distinguishes them.
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
     * @param int $payload echoed value from the matching `Ping`
     */
    public function __construct(public int $payload)
    {
    }

    public function encode(): string
    {
        return LongInt::encode($this->payload);
    }
}
