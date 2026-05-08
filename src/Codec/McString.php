<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Codec for the SLP `string` type.
 *
 * Encodes as a VarInt length prefix followed by the raw UTF-8 bytes; decoding reads the length and
 * then consumes that many bytes from the buffer.
 */
final class McString
{
    /**
     * Encode a string as a VarInt length prefix followed by the raw bytes.
     */
    public static function encode(string $value): string
    {
        return VarInt::encode(strlen($value)) . $value;
    }

    /**
     * Read a VarInt length, then consume that many bytes from the buffer as the string body.
     *
     * @throws ProtocolException if the prefix is malformed or the buffer is too short for the declared length
     */
    public static function decode(Buffer $buffer): string
    {
        $length = VarInt::decode($buffer);
        return $buffer->read($length);
    }
}
