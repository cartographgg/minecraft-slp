<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Exception\ProtocolException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VarIntTest extends TestCase
{
    #[DataProvider('boundaryValues')]
    public function testEncode(int $value, string $expected): void
    {
        $this->assertSame($expected, VarInt::encode($value));
    }

    #[DataProvider('boundaryValues')]
    public function testDecode(int $expected, string $bytes): void
    {
        $this->assertSame($expected, VarInt::decode(new Buffer($bytes)));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function boundaryValues(): array
    {
        return [
            'zero'           => [0,           "\x00"],
            'max one-byte'   => [127,         "\x7F"],
            'min two-byte'   => [128,         "\x80\x01"],
            'max two-byte'   => [16383,       "\xFF\x7F"],
            'min three-byte' => [16384,       "\x80\x80\x01"],
            'max int32'      => [2147483647,  "\xFF\xFF\xFF\xFF\x07"],
            'minus one'      => [-1,          "\xFF\xFF\xFF\xFF\x0F"],
            'min int32'      => [-2147483648, "\x80\x80\x80\x80\x08"],
        ];
    }

    public function testDecodeAdvancesBufferCursor(): void
    {
        $buffer = new Buffer("\x80\x01\xAA");
        $this->assertSame(128, VarInt::decode($buffer));
        $this->assertSame("\xAA", $buffer->read(1));
    }

    public function testDecodeOverlongVarIntThrows(): void
    {
        $buffer = new Buffer("\x80\x80\x80\x80\x80\x80");

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarInt::decode($buffer);
    }

    public function testDecodeFiveByteAllContinuationThrowsBadVarIntNotShortRead(): void
    {
        $buffer = new Buffer("\x80\x80\x80\x80\x80");

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarInt::decode($buffer);
    }

    public function testDecodeFromStreamReadsByteByByte(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x80\x01");
        rewind($stream);

        $this->assertSame(128, VarInt::decodeFromStream($stream));
        $this->assertSame(2, ftell($stream));
        fclose($stream);
    }

    public function testDecodeFromStreamThrowsOnEmptyStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        rewind($stream);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Short read: expected 1 bytes, got 0');
        VarInt::decodeFromStream($stream);
    }

    public function testDecodeFromStreamThrowsOnOverlongVarInt(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x80\x80\x80\x80\x80\x80"); // 6 continuation bytes, over the 5-byte limit
        rewind($stream);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarInt::decodeFromStream($stream);
    }

    public function testDecodeFromStreamFiveByteAllContinuationThrowsBadVarIntNotShortRead(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x80\x80\x80\x80\x80"); // exactly 5 continuation bytes (at the cap, no terminator)
        rewind($stream);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarInt::decodeFromStream($stream);
    }

    public function testDecodeFromStreamSignExtendsNegativeValues(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\xFF\xFF\xFF\xFF\x0F"); // VarInt(-1)
        rewind($stream);

        $this->assertSame(-1, VarInt::decodeFromStream($stream));
        fclose($stream);
    }

    public function testDecodeFromStreamAtSignExtensionBoundary(): void
    {
        // 0x80000000 = -2147483648 in two's complement; needs `>=` comparison to trigger sign extension.
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\x80\x80\x80\x80\x08");
        rewind($stream);

        $this->assertSame(-2147483648, VarInt::decodeFromStream($stream));
        fclose($stream);
    }
}
