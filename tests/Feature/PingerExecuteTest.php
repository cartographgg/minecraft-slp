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
use Cartograph\SLP\ExecutionContext;
use Cartograph\SLP\Failure;
use Cartograph\SLP\Packet\Packet;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Success;
use Cartograph\SLP\Transport\InMemoryTransport;
use Cartograph\SLP\Transport\Transport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PingerExecuteTest extends TestCase
{
    public function testExecuteRunsClosureAgainstConnectedConnection(): void
    {
        $statusInner = VarInt::encode(0x00) . McString::encode('{"hello":"world"}');
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;
        $conn        = new BufferConnection(preloaded: $statusFrame);

        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.1';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.1', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->execute('play.example.com', static function (ExecutionContext $ctx): string {
            $response = $ctx->connection->receive(StatusResponse::class);
            return $response->json;
        });

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertSame('{"hello":"world"}', $outcome->result);
    }

    public function testExecuteWrapsClosureReturnValueInSuccess(): void
    {
        $conn     = new BufferConnection();
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.2';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.2', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $custom  = new \stdClass();
        $outcome = $pinger->execute('play.example.com', static fn (): \stdClass => $custom);

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertSame($custom, $outcome->result);
    }

    public function testExecuteReturnsFailureDnsOnResolveFailure(): void
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
        $pinger  = new Pinger(resolver: $resolver, transport: new InMemoryTransport());
        $outcome = $pinger->execute('does-not-exist.example.com', static fn () => 'never-runs');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Dns, $outcome->type);
    }

    public function testExecuteReturnsFailureRefusedOnConnectFailure(): void
    {
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.3';
            }
        });
        $pinger  = new Pinger(resolver: $resolver, transport: new InMemoryTransport());
        $outcome = $pinger->execute('refused.example.com', static fn () => 'never-runs');

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Refused, $outcome->type);
    }

    public function testExecuteCatchesTransportExceptionFromClosure(): void
    {
        $conn     = new BufferConnection();
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.4';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.4', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->execute('timeout.example.com', static function (): string {
            throw TransportException::timeout();
        });

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::Timeout, $outcome->type);
        $this->assertInstanceOf(TransportException::class, $outcome->previous);
    }

    public function testExecuteCatchesProtocolExceptionFromClosure(): void
    {
        $conn     = new BufferConnection();
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
        $outcome = $pinger->execute('proto.example.com', static function (): string {
            throw ProtocolException::unexpectedPacket(0x00, 0x99);
        });

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::ProtocolError, $outcome->type);
        $this->assertInstanceOf(ProtocolException::class, $outcome->previous);
    }

    public function testExecuteCatchesMalformedJsonExceptionFromClosure(): void
    {
        $conn     = new BufferConnection();
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.6';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.6', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->execute('bad-json.example.com', static function (): string {
            throw MalformedJsonException::fromJsonError('Syntax error');
        });

        $this->assertInstanceOf(Failure::class, $outcome);
        $this->assertSame(ErrorType::ProtocolError, $outcome->type);
        $this->assertInstanceOf(MalformedJsonException::class, $outcome->previous);
    }

    public function testExecutePropagatesNonSlpExceptionsFromClosure(): void
    {
        $conn     = new BufferConnection();
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
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.7', 25565, $conn);

        $pinger = new Pinger(resolver: $resolver, transport: $transport);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('domain failure');

        $pinger->execute('domain-error.example.com', static function (): string {
            throw new RuntimeException('domain failure');
        });
    }

    public function testExecuteClosesConnectionEvenWhenClosureThrows(): void
    {
        $closed   = false;
        $tracking = new class($closed) implements Connection {
            /** @var Packet[] */
            public array $sent = [];

            public function __construct(public bool &$closedRef)
            {
            }

            public function send(Packet $packet): void
            {
                $this->sent[] = $packet;
            }

            public function receive(string $type): Packet
            {
                throw new RuntimeException('unused');
            }

            public function close(): void
            {
                $this->closedRef = true;
            }
        };

        $transport = new class($tracking) implements Transport {
            public function __construct(private Connection $connection)
            {
            }

            public function connect(string $host, int $port, float $timeout = 3.0): Connection
            {
                return $this->connection;
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

        $pinger = new Pinger(resolver: $resolver, transport: $transport);

        try {
            $pinger->execute('throws.example.com', static function (): string {
                throw new RuntimeException('boom');
            });
            $this->fail('expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertTrue($closed, 'expected connection->close() to be called');
    }

    public function testExecutePassesAddressAndConfigToContext(): void
    {
        $conn     = new BufferConnection();
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
        $transport->register('10.0.0.9', 30000, $conn);

        $captured = null;
        $pinger   = new Pinger(resolver: $resolver, transport: $transport);
        $pinger->execute(
            'play.example.com',
            static function (ExecutionContext $ctx) use (&$captured): string {
                $captured = $ctx;
                return 'ok';
            },
            port: 30000,
            protocolVersion: 763,
            timeout: 5.0,
        );

        $this->assertNotNull($captured);
        $this->assertSame('play.example.com', $captured->address);
        $this->assertSame(30000, $captured->port);
        $this->assertSame(763, $captured->protocolVersion);
        $this->assertSame(5.0, $captured->timeout);
        $this->assertSame('10.0.0.9', $captured->endpoint->host);
        $this->assertSame(30000, $captured->endpoint->port);
    }
}
