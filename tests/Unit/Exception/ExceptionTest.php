<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Exception;

use Cartograph\SLP\Exception\DnsException;
use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\Exception\MinecraftSlpException;
use Cartograph\SLP\Exception\ProtocolException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionTest extends TestCase
{
    public function testSlpExceptionIsRuntimeException(): void
    {
        $e = DnsException::unresolved('x');
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testDnsExceptionUnresolvedFactory(): void
    {
        $e = DnsException::unresolved('play.example.com');

        $this->assertInstanceOf(MinecraftSlpException::class, $e);
        $this->assertSame('Could not resolve address: play.example.com', $e->getMessage());
    }

    public function testProtocolExceptionUnexpectedPacket(): void
    {
        $e = ProtocolException::unexpectedPacket(0x00, 0x99);

        $this->assertInstanceOf(MinecraftSlpException::class, $e);
        $this->assertSame('Unexpected packet: expected 0x0, got 0x99', $e->getMessage());
    }

    public function testMalformedJsonExceptionFromJsonError(): void
    {
        $e = MalformedJsonException::fromJsonError('Syntax error');

        $this->assertInstanceOf(MinecraftSlpException::class, $e);
        $this->assertSame('Status JSON did not parse: Syntax error', $e->getMessage());
    }
}
