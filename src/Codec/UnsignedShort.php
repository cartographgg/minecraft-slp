<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Codec for the SLP `unsigned short` type.
 *
 * 16-bit big-endian unsigned integer; `pack('n', ...)` round-trips with `unpack('n', ...)`.
 */
final class UnsignedShort
{
    /**
     * Encode an unsigned 16-bit integer as 2 big-endian bytes.
     */
    public static function encode(int $value): string
    {
        return pack('n', $value);
    }

    /**
     * Consume 2 bytes from the buffer and decode them as an unsigned 16-bit integer.
     *
     * @throws ProtocolException if fewer than 2 bytes are available
     */
    public static function decode(Buffer $buffer): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('n', $buffer->read(2));
        return $unpacked[1];
    }
}
