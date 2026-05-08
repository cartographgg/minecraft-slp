<?php

declare(strict_types=1);

namespace Cartograph\SLP\Connection;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Packet\Packet;

/**
 * `Connection` backed by a PHP stream resource.
 *
 * The stream is typically a TCP socket from `stream_socket_client`. Translates stream-level
 * read/write failures into `TransportException` and recognises stream timeout metadata so
 * timeouts surface as `ErrorType::Timeout` rather than generic protocol errors.
 */
final class StreamConnection implements Connection
{
    /**
     * @param resource $stream open, readable/writable PHP stream resource (typically a TCP socket)
     */
    public function __construct(private $stream)
    {
    }

    /**
     * Encode `$packet` as a full framed message (length + ID + payload) and write it to the stream.
     *
     * @throws TransportException if the underlying stream rejects the write
     */
    public function send(Packet $packet): void
    {
        $inner = VarInt::encode($packet::packetId()) . $packet->encode();
        $frame = VarInt::encode(strlen($inner)) . $inner;
        $this->writeAll($frame);
    }

    /**
     * @template T of Packet
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws TransportException if the stream times out, EOFs mid-frame, or otherwise fails
     * @throws ProtocolException  if the framing is malformed or the packet ID does not match `$type`
     */
    public function receive(string $type): Packet
    {
        try {
            $length = VarInt::decodeFromStream($this->stream);
        } catch (ProtocolException $e) {
            $meta = stream_get_meta_data($this->stream);
            if (! empty($meta['timed_out']) || feof($this->stream)) {
                throw TransportException::timeout();
            }
            throw $e;
        }

        $inner = $this->readAll($length);

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
     * Close the underlying stream if it is still a resource. Idempotent.
     */
    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Write `$bytes` to the stream, asserting the entire buffer was accepted.
     *
     * @throws TransportException if `fwrite` fails or writes fewer bytes than requested
     */
    private function writeAll(string $bytes): void
    {
        $written = @fwrite($this->stream, $bytes);
        if ($written === false || $written !== strlen($bytes)) {
            throw new TransportException('Write failed', ErrorType::Other);
        }
    }

    /**
     * Read exactly `$bytes` bytes from the stream, looping over partial reads.
     *
     * @throws TransportException if the stream times out, EOFs early, or fails to read
     */
    private function readAll(int $bytes): string
    {
        $out       = '';
        $remaining = $bytes;
        while ($remaining > 0) {
            $chunk = @fread($this->stream, $remaining);
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if (! empty($meta['timed_out'])) {
                    throw TransportException::timeout();
                }
                if (feof($this->stream)) {
                    throw TransportException::timeout();
                }
                throw new TransportException('Read failed', ErrorType::Other);
            }
            $out .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $out;
    }
}
