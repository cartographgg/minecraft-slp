<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\LongInt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LongIntTest extends TestCase
{
    #[DataProvider('values')]
    public function testEncode(int $value, string $expected): void
    {
        $this->assertSame($expected, LongInt::encode($value));
    }

    #[DataProvider('values')]
    public function testDecode(int $expected, string $bytes): void
    {
        $this->assertSame($expected, LongInt::decode(new Buffer($bytes)));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function values(): array
    {
        return [
            'zero'      => [0,           "\x00\x00\x00\x00\x00\x00\x00\x00"],
            'one'       => [1,           "\x00\x00\x00\x00\x00\x00\x00\x01"],
            'max int64' => [PHP_INT_MAX, "\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF"],
            'minus one' => [-1,          "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF"],
            'min int64' => [PHP_INT_MIN, "\x80\x00\x00\x00\x00\x00\x00\x00"],
        ];
    }
}
