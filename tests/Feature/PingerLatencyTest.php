<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Clock;
use Cartograph\SLP\Codec\LongInt;
use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Success;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class PingerLatencyTest extends TestCase
{
    public function testStandardPingReturnsExactLatencyAndSendsPingPacket(): void
    {
        $serverJson  = '{"version":{"name":"1.21","protocol":763},"players":{"online":0,"max":0},"description":""}';
        $statusInner = VarInt::encode(0x00) . McString::encode($serverJson);
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;

        // Fake clock returns 1_000 then 1_050; expected latency = 50ms.
        $clock = new class implements Clock {
            /** @var list<int> */
            public array $values = [1000, 1050];

            public function monotonicMs(): int
            {
                return array_shift($this->values) ?? 0;
            }
        };

        // Pong's payload must match $start = 1000 for the latency branch to execute.
        $pongInner = VarInt::encode(0x01) . LongInt::encode(1000);
        $pongFrame = VarInt::encode(strlen($pongInner)) . $pongInner;

        $conn     = new BufferConnection(preloaded: $statusFrame . $pongFrame);
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.30';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.30', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport, clock: $clock);
        $outcome = $pinger->ping('latency.example.com');

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertInstanceOf(PingResult::class, $outcome->result);
        $this->assertSame(50, $outcome->result->latencyMs);

        // The Ping packet bytes must end the written stream: VarInt length + 0x01 packet ID + LongInt(1000).
        $expectedPingInner = VarInt::encode(0x01) . LongInt::encode(1000);
        $expectedPingFrame = VarInt::encode(strlen($expectedPingInner)) . $expectedPingInner;

        $this->assertStringEndsWith($expectedPingFrame, $conn->bytesWritten());
    }
}
