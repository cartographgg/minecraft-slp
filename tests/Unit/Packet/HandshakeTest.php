<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use PHPUnit\Framework\TestCase;

final class HandshakeTest extends TestCase
{
    public function testPacketIdIsZero(): void
    {
        $this->assertSame(0x00, Handshake::packetId());
    }

    public function testEncodeProducesSpecBytes(): void
    {
        $packet  = new Handshake(763, 'play.example.com', 25565, NextState::Status);
        $encoded = $packet->encode();

        // VarInt(763) = 0xFB 0x05
        // McString("play.example.com") = VarInt(16) + bytes = 0x10 + ASCII
        // UnsignedShort(25565) = 0x63 0xDD
        // VarInt(1) = 0x01
        $expected = "\xFB\x05\x10play.example.com\x63\xDD\x01";
        $this->assertSame($expected, $encoded);
    }

    public function testRoundTrip(): void
    {
        $original = new Handshake(763, 'play.example.com', 25565, NextState::Status);
        $decoded  = Handshake::decode($original->encode());

        $this->assertSame(763, $decoded->protocolVersion);
        $this->assertSame('play.example.com', $decoded->serverAddress);
        $this->assertSame(25565, $decoded->serverPort);
        $this->assertSame(NextState::Status, $decoded->nextState);
    }

    public function testCustomServerAddressForVerificationFlow(): void
    {
        $packet  = new Handshake(-1, 'verify-XYZ123.play.example.com', 25565, NextState::Status);
        $decoded = Handshake::decode($packet->encode());

        $this->assertSame(-1, $decoded->protocolVersion);
        $this->assertSame('verify-XYZ123.play.example.com', $decoded->serverAddress);
    }
}
