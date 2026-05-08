<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\StatusRequest;
use PHPUnit\Framework\TestCase;

final class StatusRequestTest extends TestCase
{
    public function testPacketIdIsZero(): void
    {
        $this->assertSame(0x00, StatusRequest::packetId());
    }

    public function testEncodeProducesEmptyPayload(): void
    {
        $this->assertSame('', new StatusRequest()->encode());
    }

    public function testDecodeReturnsInstance(): void
    {
        $this->assertInstanceOf(StatusRequest::class, StatusRequest::decode(''));
    }
}
