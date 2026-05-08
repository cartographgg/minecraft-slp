<?php

declare(strict_types=1);

namespace Cartograph\SLP\Connection;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Packet\Packet;

/**
 * In-memory `Connection` used by tests.
 *
 * Writes accumulate into a string accessible via `bytesWritten()`; reads are served from a preloaded
 * byte string. No network I/O happens, so `close()` is a no-op.
 */
final class BufferConnection implements Connection
{
    /**
     * Bytes accumulated by `send()`, in the same wire format `StreamConnection` would emit.
     */
    private string $written = '';

    /**
     * Cursor over the bytes a test preloaded for `receive()` to consume.
     */
    private Buffer $incoming;

    /**
     * @param string $preloaded bytes that `receive()` will consume, framed exactly as a real server would emit
     */
    public function __construct(string $preloaded = '')
    {
        $this->incoming = new Buffer($preloaded);
    }

    /**
     * Encode `$packet` (length prefix + ID + payload) and append to the in-memory write buffer.
     */
    public function send(Packet $packet): void
    {
        $inner = VarInt::encode($packet::packetId()) . $packet->encode();
        $this->written .= VarInt::encode(strlen($inner)) . $inner;
    }

    /**
     * @template T of Packet
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws ProtocolException if the preloaded bytes are malformed or the packet ID does not match `$type`
     */
    public function receive(string $type): Packet
    {
        $length     = VarInt::decode($this->incoming);
        $inner      = $this->incoming->read($length);
        $innerBuf   = new Buffer($inner);
        $actualId   = VarInt::decode($innerBuf);
        $expectedId = $type::packetId();
        if ($actualId !== $expectedId) {
            throw ProtocolException::unexpectedPacket($expectedId, $actualId);
        }
        $remaining = $innerBuf->read($innerBuf->remaining());
        return $type::decode($remaining);
    }

    /**
     * No-op: the in-memory connection holds no real resources.
     */
    public function close(): void
    {
    }

    /**
     * Bytes that `send()` has accumulated so far. Tests use this to assert on the wire format.
     */
    public function bytesWritten(): string
    {
        return $this->written;
    }
}
