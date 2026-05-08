<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Connection\Connection;
use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Failure;
use Cartograph\SLP\Packet\Packet;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Transport\InMemoryTransport;
use Cartograph\SLP\Transport\Transport;
use PHPUnit\Framework\TestCase;

final class PingerErrorPathsTest extends TestCase
{
    public function testDnsFailureReturnsFailureDns(): void
    {
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return null;
            }
        });
        $pinger = new Pinger(resolver: $resolver, transport: new InMemoryTransport());

        $outcome = $pinger->ping('does-not-exist.example.com');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Dns, $outcome->type);
    }

    public function testConnectRefusedReturnsFailureRefused(): void
    {
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.7';
            }
        });
        $pinger = new Pinger(resolver: $resolver, transport: new InMemoryTransport());

        $outcome = $pinger->ping('refused.example.com');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Refused, $outcome->type);
    }

    public function testTimeoutDuringReadReturnsFailureTimeout(): void
    {
        $transport = new class implements Transport {
            public function connect(string $host, int $port, float $timeout = 3.0): Connection
            {
                return new class implements Connection {
                    public function send(Packet $packet): void
                    {
                    }

                    public function receive(string $type): Packet
                    {
                        throw TransportException::timeout();
                    }

                    public function close(): void
                    {
                    }
                };
            }
        };

        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.8';
            }
        });

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->ping('timeout.example.com');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Timeout, $outcome->type);
    }

    public function testMalformedJsonReturnsFailureProtocolError(): void
    {
        $statusInner = VarInt::encode(0x00) . McString::encode('not-json{{{{');
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;
        $conn        = new BufferConnection(preloaded: $statusFrame);

        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.9';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.9', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->ping('bad-json.example.com');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::ProtocolError, $outcome->type);
        $this->assertInstanceOf(MalformedJsonException::class, $outcome->previous);
    }

    public function testUnexpectedPacketIdReturnsFailureProtocolError(): void
    {
        // Server returns a frame with packet ID 0x99 instead of 0x00.
        $inner = VarInt::encode(0x99); // VarInt(0x99) = 0x99 0x01 (2 bytes)
        $frame = VarInt::encode(strlen($inner)) . $inner;
        $conn  = new BufferConnection(preloaded: $frame);

        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.10';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.10', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->ping('wrong-id.example.com');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::ProtocolError, $outcome->type);
        $this->assertInstanceOf(ProtocolException::class, $outcome->previous);
    }
}
