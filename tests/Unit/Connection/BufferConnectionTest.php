<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Connection;

use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use PHPUnit\Framework\TestCase;

final class BufferConnectionTest extends TestCase
{
    public function testSendFramesPacketWithLengthAndId(): void
    {
        $conn = new BufferConnection();
        $conn->send(new StatusRequest());

        // StatusRequest has empty payload. Wire frame: VarInt(length=1) + VarInt(id=0x00) = 0x01 0x00.
        $this->assertSame("\x01\x00", $conn->bytesWritten());
    }

    public function testSendHandshakePacketProducesExpectedBytes(): void
    {
        $conn = new BufferConnection();
        $conn->send(new Handshake(763, 'play.example.com', 25565, NextState::Status));

        // Payload: 0xFB 0x05 (VarInt 763), 0x10 (string len 16), ASCII bytes, 0x63 0xDD (port), 0x01 (NextState)
        // Inner = ID byte (0x00) + payload = 23 bytes
        // Length VarInt(23) = 0x17
        $payload  = "\xFB\x05\x10play.example.com\x63\xDD\x01";
        $expected = "\x17\x00" . $payload;
        $this->assertSame($expected, $conn->bytesWritten());
    }

    public function testReceiveDecodesFramedPacket(): void
    {
        // Preload a StatusResponse: payload = McString("{}") = 0x02 + "{}"; inner = ID(0x00) + payload = 4 bytes; length = 0x04
        $conn = new BufferConnection(preloaded: "\x04\x00\x02{}");

        $response = $conn->receive(StatusResponse::class);

        $this->assertSame('{}', $response->json);
    }

    public function testReceiveUnexpectedPacketIdThrows(): void
    {
        // Preload bytes claiming to be packet ID 0x19.
        // "\x02" = length 2; "\x99\x00" = VarInt(25) because 0x99 low 7 bits = 0x19 with continuation bit,
        // followed by 0x00 which has no continuation; total value = 25 = 0x19.
        $conn = new BufferConnection(preloaded: "\x02\x99\x00");

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unexpected packet: expected 0x0, got 0x19');
        $conn->receive(StatusResponse::class);
    }

    public function testCloseDoesNotThrowAndIsIdempotent(): void
    {
        $conn = new BufferConnection();
        $conn->close();
        $conn->close();

        $this->expectNotToPerformAssertions();
    }
}
