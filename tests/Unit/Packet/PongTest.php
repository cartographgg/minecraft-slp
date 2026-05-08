<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\Pong;
use PHPUnit\Framework\TestCase;

final class PongTest extends TestCase
{
    public function testPacketIdIsOne(): void
    {
        $this->assertSame(0x01, Pong::packetId());
    }

    public function testRoundTrip(): void
    {
        $original = new Pong(987654321);
        $decoded  = Pong::decode($original->encode());

        $this->assertSame(987654321, $decoded->payload);
    }
}
