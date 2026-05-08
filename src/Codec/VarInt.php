<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Codec for the SLP `VarInt` type.
 *
 * A 7-bits-per-byte signed 32-bit integer with a continuation bit. Encoding masks the value to
 * 32 bits so PHP's arithmetic right-shift behaves like a logical shift; decoding sign-extends the
 * 32-bit unsigned representation back into PHP's native signed int. Hard-capped at 5 bytes on the
 * wire, matching the upstream Minecraft protocol.
 */
final class VarInt
{
    /**
     * Maximum encoded length for a 32-bit VarInt.
     */
    private const int MAX_BYTES = 5;

    /**
     * Encode a 32-bit integer as a VarInt (1 to 5 bytes).
     */
    public static function encode(int $value): string
    {
        $value &= 0xFFFFFFFF; // truncate to 32-bit unsigned (so >> below is safe)
        $out = '';
        while (true) {
            if (($value & ~0x7F) === 0) {
                return $out . chr($value & 0xFF);
            }
            $out .= chr(($value & 0x7F) | 0x80);
            $value >>= 7; // safe because $value is non-negative after the mask above
        }
    }

    /**
     * Consume a VarInt from the buffer and decode it as a signed 32-bit integer.
     *
     * @throws ProtocolException if the VarInt exceeds 5 bytes or the buffer ends mid-value
     */
    public static function decode(Buffer $buffer): int
    {
        $value    = 0;
        $position = 0;
        $read     = 0;
        while (true) {
            $byte = ord($buffer->read(1));
            $value |= (($byte & 0x7F) << $position);
            ++$read;
            if (($byte & 0x80) === 0) {
                break;
            }
            if ($read >= self::MAX_BYTES) {
                throw ProtocolException::badVarInt();
            }
            $position += 7;
        }
        // Sign-extend 32-bit unsigned back into PHP's native signed int
        if ($value >= 0x80000000) {
            $value -= 0x100000000;
        }
        return $value;
    }

    /**
     * Read a VarInt directly from a stream resource, byte by byte.
     *
     * Used by `StreamConnection` so the length prefix can be read without first buffering an
     * unknown number of bytes; without this seam the decoder would have to over-read.
     *
     * @param resource $stream
     *
     * @throws ProtocolException if the VarInt exceeds 5 bytes or the stream ends mid-value
     */
    public static function decodeFromStream($stream): int
    {
        $value    = 0;
        $position = 0;
        $read     = 0;
        while (true) {
            $byte = fread($stream, 1);
            if ($byte === false || $byte === '') {
                throw ProtocolException::shortRead(1, 0);
            }
            $b = ord($byte);
            $value |= (($b & 0x7F) << $position);
            ++$read;
            if (($b & 0x80) === 0) {
                break;
            }
            if ($read >= self::MAX_BYTES) {
                throw ProtocolException::badVarInt();
            }
            $position += 7;
        }
        if ($value >= 0x80000000) {
            $value -= 0x100000000;
        }
        return $value;
    }
}
