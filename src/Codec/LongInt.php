<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Codec for the SLP `long` type.
 *
 * Encodes and decodes a signed 64-bit big-endian integer using PHP's `pack`/`unpack` with format `J`.
 */
final class LongInt
{
    /**
     * Encode a signed 64-bit integer as 8 big-endian bytes.
     */
    public static function encode(int $value): string
    {
        return pack('J', $value);
    }

    /**
     * Consume 8 bytes from the buffer and decode them as a signed 64-bit integer.
     *
     * @throws ProtocolException if fewer than 8 bytes are available
     */
    public static function decode(Buffer $buffer): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('J', $buffer->read(8));
        return $unpacked[1];
    }
}
