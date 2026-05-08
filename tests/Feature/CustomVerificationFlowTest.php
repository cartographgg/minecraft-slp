<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\UnsignedShort;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Packet\Handshake;
use Cartograph\SLP\Packet\NextState;
use Cartograph\SLP\Packet\StatusRequest;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Resolver\Endpoint;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class CustomVerificationFlowTest extends TestCase
{
    public function testCustomHostnameRidesInHandshake(): void
    {
        $serverJson  = '{"version":{"name":"1.21","protocol":763},"players":{"online":0,"max":0},"description":""}';
        $statusInner = VarInt::encode(0x00) . McString::encode($serverJson);
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;

        $conn      = new BufferConnection(preloaded: $statusFrame);
        $endpoint  = new Endpoint('10.0.0.11', 25565);
        $transport = new InMemoryTransport();
        $transport->register($endpoint->host, $endpoint->port, $conn);

        $verifyToken      = 'XYZ123';
        $customServerAddr = "verify-{$verifyToken}.{$endpoint->host}";

        $opened = $transport->connect($endpoint->host, $endpoint->port, 3.0);
        $opened->send(new Handshake(
            protocolVersion: -1,
            serverAddress: $customServerAddr,
            serverPort: $endpoint->port,
            nextState: NextState::Status,
        ));
        $opened->send(new StatusRequest());
        $response = $opened->receive(StatusResponse::class);

        $this->assertSame($serverJson, $response->json);

        // Confirm the handshake frame's exact bytes (which also confirms the custom hostname is present).
        $written         = $conn->bytesWritten();
        $expectedPayload = VarInt::encode(-1)
            . McString::encode($customServerAddr)
            . UnsignedShort::encode(25565)
            . VarInt::encode(1);
        $expectedInner = VarInt::encode(0x00) . $expectedPayload;
        $expectedFrame = VarInt::encode(strlen($expectedInner)) . $expectedInner;

        $this->assertStringStartsWith($expectedFrame . "\x01\x00", $written);
    }
}
