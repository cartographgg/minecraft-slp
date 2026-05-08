<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Connection;

use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\UnsignedShort;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\StreamConnection;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use PHPUnit\Framework\TestCase;

/**
 * Stream wrapper that accepts only 1 byte per fwrite() call.
 *
 * PHP's `fwrite` retries internally when a stream wrapper returns less than the input length,
 * so this wrapper drives that retry path and lets tests assert that every byte arrived once.
 */
final class PartialWriteStream
{
    public static int $totalWritten = 0;

    public static string $assembled = '';

    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        self::$totalWritten = 0;
        self::$assembled    = '';

        return true;
    }

    public function stream_write(string $data): int
    {
        if ($data === '') {
            return 0;
        }
        self::$assembled .= $data[0];
        ++self::$totalWritten;

        return 1;
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /** @return array<mixed> */
    public function stream_stat(): array
    {
        return [];
    }

    /** @return array<mixed> */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }
}

/**
 * Stream wrapper that serves at most 1 byte per fread() call, forcing readAll() to loop.
 */
final class PartialReadStream
{
    public static string $preload = '';

    /** @var resource|null */
    public $context;

    private string $buffer = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->buffer = self::$preload;

        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->buffer === '') {
            return '';
        }
        $chunk        = $this->buffer[0];
        $this->buffer = substr($this->buffer, 1);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->buffer === '';
    }

    public function stream_close(): void
    {
    }

    /** @return array<mixed> */
    public function stream_stat(): array
    {
        return [];
    }

    /** @return array<mixed> */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }
}

/**
 * Stream wrapper where fwrite() always fails (returns 0), triggering the "Write failed" branch.
 */
final class FailingWriteStream
{
    /** @var resource|null */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return 0; // Return 0 to trigger the "Write failed" branch in writeAll()
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /** @return array<mixed> */
    public function stream_stat(): array
    {
        return [];
    }

    /** @return array<mixed> */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }
}

/**
 * Stream wrapper that serves preloaded bytes then returns false from fread,
 * while reporting itself as not EOF and not timed_out.
 *
 * Preloaded bytes are set via the static $preload property before fopen().
 */
final class ReadFailStream
{
    public static string $preload = '';

    /** @var resource|null */
    public $context;

    private string $buffer = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->buffer = self::$preload;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->buffer !== '') {
            $chunk        = substr($this->buffer, 0, $count);
            $this->buffer = substr($this->buffer, strlen($chunk));

            return $chunk;
        }

        return false; // Simulate a read failure without EOF or timeout
    }

    public function stream_write(string $data): int
    {
        return strlen($data);
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return false; // Not EOF, forces the "Read failed" / re-throw branches
    }

    /** @return array<mixed> */
    public function stream_stat(): array
    {
        return [];
    }

    /** @return array<mixed> */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }
}

final class StreamConnectionTest extends TestCase
{
    public function testSendWritesFramedBytesToStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        $conn   = new StreamConnection($stream);

        $conn->send(new StatusRequest());
        rewind($stream);

        $this->assertSame("\x01\x00", stream_get_contents($stream));
        fclose($stream);
    }

    public function testSendWritesPacketIdBeforePayload(): void
    {
        $stream = fopen('php://memory', 'r+');
        $conn   = new StreamConnection($stream);

        $conn->send(new Handshake(
            protocolVersion: 763,
            serverAddress: 'play.example.com',
            serverPort: 25565,
            nextState: NextState::Status,
        ));
        rewind($stream);

        $payload = VarInt::encode(763)
            . McString::encode('play.example.com')
            . UnsignedShort::encode(25565)
            . VarInt::encode(NextState::Status->value);
        $inner    = "\x00" . $payload;
        $expected = VarInt::encode(strlen($inner)) . $inner;

        $this->assertSame($expected, stream_get_contents($stream));
        fclose($stream);
    }

    public function testReceiveReadsFramedPacketFromStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        // Preload a StatusResponse with payload "{}": inner = ID(0x00) + McString("{}") = 4 bytes; length = 0x04
        fwrite($stream, "\x04\x00\x02{}");
        rewind($stream);

        $conn     = new StreamConnection($stream);
        $response = $conn->receive(StatusResponse::class);

        $this->assertSame('{}', $response->json);
        fclose($stream);
    }

    public function testReceiveTimeoutOnStreamThrowsTransportException(): void
    {
        // Empty memory stream: read will return '' (EOF), which the implementation
        // treats as a timeout-like short read via the catch block (feof check).
        $stream = fopen('php://memory', 'r+');
        rewind($stream);

        $conn = new StreamConnection($stream);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Read timed out');
        $conn->receive(StatusResponse::class);
        fclose($stream);
    }

    public function testCloseClosesUnderlyingStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        $conn   = new StreamConnection($stream);

        $conn->close();

        $this->assertFalse(is_resource($stream));
    }

    public function testSendDeliversEveryByteThroughOneBytePerCallWrapper(): void
    {
        stream_wrapper_register('partialwrite', PartialWriteStream::class);
        $stream = fopen('partialwrite://test', 'w');
        $conn   = new StreamConnection($stream);

        // Handshake produces a multi-byte frame. PHP's fwrite drives the wrapper's
        // 1-byte-per-call retry; the assembled bytes must equal the full frame.
        $conn->send(new Handshake(
            protocolVersion: 763,
            serverAddress: 'play.example.com',
            serverPort: 25565,
            nextState: NextState::Status,
        ));

        $payload = VarInt::encode(763)
            . McString::encode('play.example.com')
            . UnsignedShort::encode(25565)
            . VarInt::encode(NextState::Status->value);
        $inner    = "\x00" . $payload;
        $expected = VarInt::encode(strlen($inner)) . $inner;

        $this->assertSame(strlen($expected), PartialWriteStream::$totalWritten);
        $this->assertSame($expected, PartialWriteStream::$assembled);

        fclose($stream);
        stream_wrapper_unregister('partialwrite');
    }

    public function testReadAllReassemblesPartialChunks(): void
    {
        // Frame: length=0x06, inner = 0x00 (StatusResponse) + McString("hi"): \x02hi
        $frame = "\x06\x00\x02hi\x00\x00\x00"; // padding so PartialReadStream doesn't EOF mid-frame
        stream_wrapper_register('partialread', PartialReadStream::class);
        PartialReadStream::$preload = $frame;
        $stream                     = fopen('partialread://test', 'r');
        $conn                       = new StreamConnection($stream);

        $response = $conn->receive(StatusResponse::class);

        $this->assertSame('hi', $response->json);

        fclose($stream);
        stream_wrapper_unregister('partialread');
    }

    public function testWriteAllThrowsOnWriteFailure(): void
    {
        stream_wrapper_register('failingwrite', FailingWriteStream::class);
        $stream = fopen('failingwrite://test', 'w');
        $conn   = new StreamConnection($stream);

        $this->expectException(TransportException::class);
        $conn->send(new StatusRequest());

        fclose($stream);
        stream_wrapper_unregister('failingwrite');
    }

    public function testReadAllOnEofMidReadThrowsTransportException(): void
    {
        // Frame claims length=10 but only 1 payload byte follows the length VarInt.
        // The readAll() loop will hit EOF mid-read and throw TransportException.
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x0A\x00"); // length=10, then only 1 byte of payload
        rewind($stream);

        $conn = new StreamConnection($stream);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Read timed out');
        $conn->receive(StatusResponse::class);
        fclose($stream);
    }

    public function testReceiveTimeoutInLengthVarIntPathThrowsTimeoutException(): void
    {
        // Real socket pair so stream_get_meta_data can actually surface timed_out=true.
        // The server writes nothing, so VarInt::decodeFromStream times out at the first byte,
        // exercising the catch block in `receive()` (the `decodeFromStream` path).
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            $this->markTestSkipped('stream_socket_pair unavailable on this platform');
        }
        [$client, $server] = $pair;

        stream_set_timeout($client, 0, 50_000);
        $conn = new StreamConnection($client);

        try {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('Read timed out');
            $conn->receive(StatusResponse::class);
        } finally {
            fclose($server);
        }
    }

    public function testReadAllOnStreamTimeoutFlagThrowsTimeoutException(): void
    {
        // Server writes a valid length VarInt (10), then writes nothing more. The client reads
        // the length successfully, then enters readAll() and times out, exercising the
        // timed_out branch in readAll() itself (distinct from the receive() catch path).
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            $this->markTestSkipped('stream_socket_pair unavailable on this platform');
        }
        [$client, $server] = $pair;

        fwrite($server, "\x0A"); // length VarInt = 10; no further data
        fflush($server);

        stream_set_timeout($client, 0, 50_000);
        $conn = new StreamConnection($client);

        try {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage('Read timed out');
            $conn->receive(StatusResponse::class);
        } finally {
            fclose($server);
        }
    }

    public function testReadAllThrowsOnReadFailureWithoutEofOrTimeout(): void
    {
        // ReadFailStream serves "\x0A" (length=10) then returns false (not EOF, not timed_out).
        // This hits the "Read failed" branch in readAll().
        stream_wrapper_register('readfail', ReadFailStream::class);
        ReadFailStream::$preload = "\x0A"; // VarInt(10) = length
        $stream                  = fopen('readfail://test', 'r+');
        $conn                    = new StreamConnection($stream);

        $this->expectException(TransportException::class);
        $conn->receive(StatusResponse::class);

        fclose($stream);
        stream_wrapper_unregister('readfail');
    }

    public function testReceiveRethrowsProtocolExceptionWhenNotTimeoutOrEof(): void
    {
        // ReadFailStream with 6 continuation bytes causes badVarInt ProtocolException in decodeFromStream.
        // The stream is not at EOF and not timed_out, so the catch block re-throws the ProtocolException.
        stream_wrapper_register('protocolerrorread', ReadFailStream::class);
        ReadFailStream::$preload = "\x80\x80\x80\x80\x80\x80"; // 6 bytes, triggers overlong VarInt
        $stream                  = fopen('protocolerrorread://test', 'r+');
        $conn                    = new StreamConnection($stream);

        $this->expectException(ProtocolException::class);
        $conn->receive(StatusResponse::class);

        fclose($stream);
        stream_wrapper_unregister('protocolerrorread');
    }

    public function testReceiveUnexpectedPacketIdThrowsProtocolException(): void
    {
        // Preload a frame with packet ID 0x19 (VarInt \x99\x00) where StatusResponse expects 0x00.
        // length=2 (\x02), inner=\x99\x00 which VarInt-decodes to 25 (0x19).
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x02\x99\x00");
        rewind($stream);

        $conn = new StreamConnection($stream);
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unexpected packet: expected 0x0, got 0x19');
        $conn->receive(StatusResponse::class);
        fclose($stream);
    }
}
