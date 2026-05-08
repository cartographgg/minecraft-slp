<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\VarLong;
use Cartograph\SLP\Exception\ProtocolException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VarLongTest extends TestCase
{
    #[DataProvider('boundaryValues')]
    public function testEncode(int $value, string $expected): void
    {
        $this->assertSame($expected, VarLong::encode($value));
    }

    #[DataProvider('boundaryValues')]
    public function testDecode(int $expected, string $bytes): void
    {
        $this->assertSame($expected, VarLong::decode(new Buffer($bytes)));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function boundaryValues(): array
    {
        return [
            'zero'         => [0, "\x00"],
            'max one-byte' => [127, "\x7F"],
            'min two-byte' => [128, "\x80\x01"],
            'max int32'    => [2147483647, "\xFF\xFF\xFF\xFF\x07"],
            'max int64'    => [PHP_INT_MAX, "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\x7F"],
            'minus one'    => [-1, "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\x01"],
            'min int64'    => [PHP_INT_MIN, "\x80\x80\x80\x80\x80\x80\x80\x80\x80\x01"],
        ];
    }

    public function testDecodeOverlongThrows(): void
    {
        $buffer = new Buffer(str_repeat("\x80", 11));

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarLong::decode($buffer);
    }

    public function testDecodeTenByteAllContinuationThrowsBadVarIntNotShortRead(): void
    {
        $buffer = new Buffer(str_repeat("\x80", 10));

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('VarInt is too long or malformed');
        VarLong::decode($buffer);
    }
}
