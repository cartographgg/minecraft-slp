<?php

declare(strict_types=1);

namespace Cartograph\SLP\Exception;

/**
 * Raised when SLP framing or packet contents violate the protocol.
 *
 * Covers short reads, malformed VarInts, and packet IDs that disagree with the type the caller
 * asked to receive. Maps to `ErrorType::ProtocolError`.
 */
class ProtocolException extends MinecraftSlpException
{
    /**
     * The buffer or stream had fewer bytes than the codec needed to consume.
     */
    public static function shortRead(int $expectedBytes, int $actualBytes): self
    {
        return new self("Short read: expected {$expectedBytes} bytes, got {$actualBytes}");
    }

    /**
     * A VarInt/VarLong exceeded its maximum encoded length without terminating.
     */
    public static function badVarInt(): self
    {
        return new self('VarInt is too long or malformed');
    }

    /**
     * A packet's wire ID did not match the type the caller asked to receive.
     */
    public static function unexpectedPacket(int $expectedId, int $actualId): self
    {
        return new self(sprintf(
            'Unexpected packet: expected 0x%X, got 0x%X',
            $expectedId,
            $actualId,
        ));
    }
}
