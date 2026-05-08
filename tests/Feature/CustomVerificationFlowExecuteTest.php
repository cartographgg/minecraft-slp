<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\UnsignedShort;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\ExecutionContext;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Success;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class CustomVerificationFlowExecuteTest extends TestCase
{
    public function testCustomHostnameRidesInHandshakeViaExecute(): void
    {
        $serverJson  = '{"version":{"name":"1.21","protocol":763},"players":{"online":0,"max":0},"description":""}';
        $statusInner = VarInt::encode(0x00) . McString::encode($serverJson);
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;

        $conn     = new BufferConnection(preloaded: $statusFrame);
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.13';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.13', 25565, $conn);

        $verifyToken = 'XYZ123';
        $pinger      = new Pinger(resolver: $resolver, transport: $transport);

        $outcome = $pinger->execute(
            'play.example.com',
            static function (ExecutionContext $ctx) use ($verifyToken): string {
                $ctx->connection->send(new Handshake(
                    protocolVersion: $ctx->protocolVersion,
                    serverAddress: "verify-{$verifyToken}.{$ctx->endpoint->host}",
                    serverPort: $ctx->endpoint->port,
                    nextState: NextState::Status,
                ));
                $ctx->connection->send(new StatusRequest());

                return $ctx->connection->receive(StatusResponse::class)->json;
            },
        );

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertSame($serverJson, $outcome->result);

        $written         = $conn->bytesWritten();
        $expectedAddr    = "verify-{$verifyToken}.10.0.0.13";
        $expectedPayload = VarInt::encode(-1)
            . McString::encode($expectedAddr)
            . UnsignedShort::encode(25565)
            . VarInt::encode(1);
        $expectedInner = VarInt::encode(0x00) . $expectedPayload;
        $expectedFrame = VarInt::encode(strlen($expectedInner)) . $expectedInner;

        $this->assertSame($expectedFrame . "\x01\x00", $written);
    }
}
