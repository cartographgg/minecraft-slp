<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\Ping;
use PHPUnit\Framework\TestCase;

final class PingTest extends TestCase
{
    public function testPacketIdIsOne(): void
    {
        $this->assertSame(0x01, Ping::packetId());
    }

    public function testEncodeWritesLong(): void
    {
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00\x2A", new Ping(42)->encode());
    }

    public function testRoundTrip(): void
    {
        $original = new Ping(123456789);
        $decoded  = Ping::decode($original->encode());

        $this->assertSame(123456789, $decoded->payload);
    }
}
