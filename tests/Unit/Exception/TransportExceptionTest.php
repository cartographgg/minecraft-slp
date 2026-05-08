<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Exception;

use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\MinecraftSlpException;
use Cartograph\SLP\Exception\TransportException;
use PHPUnit\Framework\TestCase;

final class TransportExceptionTest extends TestCase
{
    public function testIsSlpException(): void
    {
        $e = new TransportException('boom', ErrorType::Other);
        $this->assertInstanceOf(MinecraftSlpException::class, $e);
    }

    public function testCarriesErrorType(): void
    {
        $e = new TransportException('boom', ErrorType::Refused);
        $this->assertSame(ErrorType::Refused, $e->type);
    }

    public function testTimeoutFactory(): void
    {
        $e = TransportException::timeout();
        $this->assertSame(ErrorType::Timeout, $e->type);
        $this->assertSame('Read timed out', $e->getMessage());
        $this->assertSame(0, $e->getCode());
    }

    public function testCodeIsAlwaysZero(): void
    {
        $this->assertSame(0, new TransportException('boom', ErrorType::Other)->getCode());
        $this->assertSame(0, TransportException::connect('host', 25565, SOCKET_ECONNREFUSED, 'Connection refused')->getCode());
    }

    public function testConnectFactoryMapsRefused(): void
    {
        $e = TransportException::connect('host', 25565, SOCKET_ECONNREFUSED, 'Connection refused');

        $this->assertSame(ErrorType::Refused, $e->type);
        $this->assertSame(
            'Connect to host:25565 failed: Connection refused (' . SOCKET_ECONNREFUSED . ')',
            $e->getMessage(),
        );
    }

    public function testConnectFactoryMapsTimeout(): void
    {
        $e = TransportException::connect('host', 25565, SOCKET_ETIMEDOUT, 'Operation timed out');

        $this->assertSame(ErrorType::Timeout, $e->type);
    }

    public function testConnectFactoryFallsThroughToOther(): void
    {
        $e = TransportException::connect('host', 25565, 9999, 'unknown errno');

        $this->assertSame(ErrorType::Other, $e->type);
    }
}
