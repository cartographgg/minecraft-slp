<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\StatusResponse;
use PHPUnit\Framework\TestCase;

final class StatusResponseTest extends TestCase
{
    public function testPacketIdIsZero(): void
    {
        $this->assertSame(0x00, StatusResponse::packetId());
    }

    public function testEncodeWritesVarIntLengthThenJsonBytes(): void
    {
        $packet = new StatusResponse('{"hello":"world"}');
        $this->assertSame("\x11" . '{"hello":"world"}', $packet->encode());
    }

    public function testRoundTrip(): void
    {
        $original = new StatusResponse('{"hello":"world"}');
        $decoded  = StatusResponse::decode($original->encode());

        $this->assertSame('{"hello":"world"}', $decoded->json);
    }

    public function testRoundTripWithLongJsonOver127Bytes(): void
    {
        $json     = '{"x":"' . str_repeat('a', 200) . '"}';
        $original = new StatusResponse($json);
        $decoded  = StatusResponse::decode($original->encode());

        $this->assertSame($json, $decoded->json);
    }
}
