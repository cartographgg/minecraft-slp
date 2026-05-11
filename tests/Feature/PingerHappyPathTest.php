<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Codec\LongInt;
use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\UnsignedShort;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Success;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class PingerHappyPathTest extends TestCase
{
    public function testStandardPingReturnsSuccessWithDecodedFields(): void
    {
        $serverJson = json_encode([
            'version'     => ['name' => '1.21.1', 'protocol' => 763],
            'players'     => ['online' => 5,  'max' => 100, 'sample' => []],
            'description' => 'Example Server',
        ], JSON_THROW_ON_ERROR);

        // Build the bytes a real server would send: StatusResponse frame + Pong frame.
        $statusPayload = McString::encode($serverJson);
        $statusInner   = VarInt::encode(0x00) . $statusPayload;
        $statusFrame   = VarInt::encode(strlen($statusInner)) . $statusInner;

        // Pinger sends Ping(t); we preload Pong with payload 0 (mismatched), so latencyMs ends up null.
        $pongPayload = LongInt::encode(0);
        $pongInner   = VarInt::encode(0x01) . $pongPayload;
        $pongFrame   = VarInt::encode(strlen($pongInner)) . $pongInner;

        $conn = new BufferConnection(preloaded: $statusFrame . $pongFrame);

        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.5';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.5', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->ping('play.example.com');

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertInstanceOf(PingResult::class, $outcome->result);
        $this->assertSame('1.21.1', $outcome->result->version->name);
        $this->assertSame(5, $outcome->result->players->online);
        $this->assertSame('Example Server', $outcome->result->description->plainText);

        // Verify exact bytes were sent: Handshake(-1, 'play.example.com', 25565, Status), StatusRequest, Ping(t).
        // The handshake echoes the caller's original address, not the resolved IP — proxies (BungeeCord,
        // Velocity, TCPShield) route on this field, so substituting the post-DNS IP would defeat virtual hosting.
        $expectedHandshakePayload = VarInt::encode(-1)
            . McString::encode('play.example.com')
            . UnsignedShort::encode(25565)
            . VarInt::encode(1);
        $expectedHandshakeInner = VarInt::encode(0x00) . $expectedHandshakePayload;
        $expectedHandshakeFrame = VarInt::encode(strlen($expectedHandshakeInner)) . $expectedHandshakeInner;
        $expectedStatusReqFrame = "\x01\x00";

        $written = $conn->bytesWritten();
        $this->assertStringStartsWith($expectedHandshakeFrame . $expectedStatusReqFrame, $written);
    }
}
