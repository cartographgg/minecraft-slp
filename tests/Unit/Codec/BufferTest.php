<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Exception\ProtocolException;
use PHPUnit\Framework\TestCase;

final class BufferTest extends TestCase
{
    public function testReadsBytesAndAdvancesCursor(): void
    {
        $buffer = new Buffer("\x01\x02\x03\x04");

        $this->assertSame("\x01\x02", $buffer->read(2));
        $this->assertSame(2, $buffer->remaining());
        $this->assertSame("\x03\x04", $buffer->read(2));
        $this->assertSame(0, $buffer->remaining());
        $this->assertTrue($buffer->isAtEnd());
    }

    public function testWriteAppendsToInternalBuffer(): void
    {
        $buffer = new Buffer();
        $buffer->write("\xAA");
        $buffer->write("\xBB\xCC");

        $this->assertSame("\xAA\xBB\xCC", $buffer->bytes());
    }

    public function testReadingPastEndThrowsProtocolException(): void
    {
        $buffer = new Buffer("\x01");

        $this->expectException(ProtocolException::class);
        $buffer->read(2);
    }

    public function testReadingPastRemainingAfterCursorAdvanceThrows(): void
    {
        $buffer = new Buffer("\x01\x02\x03\x04");
        $buffer->read(2);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Short read: expected 3 bytes, got 2');
        $buffer->read(3);
    }

    public function testEmptyBufferIsAtEnd(): void
    {
        $this->assertTrue(new Buffer()->isAtEnd());
    }
}
