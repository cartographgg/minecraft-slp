<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Codec for the SLP `VarLong` type.
 *
 * A 7-bits-per-byte signed 64-bit integer with a continuation bit. Distinct from `VarInt` in
 * allowing up to 10 bytes on the wire; encoding uses an explicit logical-right-shift simulation
 * to dodge PHP's arithmetic shift semantics on signed 64-bit values.
 */
final class VarLong
{
    /**
     * Maximum encoded length for a 64-bit VarLong.
     */
    private const int MAX_BYTES = 10;

    /**
     * Encode a signed 64-bit int as a Minecraft VarLong.
     *
     * PHP's `>>` is arithmetic (sign-extends), so to emulate logical right-shift we mask
     * off the top 7 bits the sign-fill would otherwise leave. PHP_INT_SIZE is 8 on
     * supported 64-bit builds, so the shift width is 64 - 7 = 57.
     */
    public static function encode(int $value): string
    {
        $out = '';
        while (true) {
            $byte = $value        & 0x7F;
            $next = ($value >> 7) & ~(0x7F << 57);
            if ($next === 0) {
                return $out . chr($byte);
            }
            $out .= chr($byte | 0x80);
            $value = $next;
        }
    }

    /**
     * Consume a VarLong from the buffer and decode it as a signed 64-bit integer.
     *
     * @throws ProtocolException if the VarLong exceeds 10 bytes or the buffer ends mid-value
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
        return $value;
    }
}
