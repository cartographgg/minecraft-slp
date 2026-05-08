<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Wire-level Minecraft SLP packet.
 *
 * `packetId()` is the type's identifier as it appears on the wire. `encode()`/`decode()` deal only
 * with the payload, not the length prefix or packet ID; those are handled by the `Connection`
 * framing layer.
 */
interface Packet
{
    /**
     * The wire-level identifier the SLP protocol uses for this packet type.
     */
    public static function packetId(): int;

    /**
     * Decode the packet's payload, excluding the length prefix and packet ID.
     *
     * @throws ProtocolException if `$payload` is malformed for this packet type
     */
    public static function decode(string $payload): static;

    /**
     * Encode the packet's payload, excluding the length prefix and packet ID.
     */
    public function encode(): string;
}
