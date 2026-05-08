<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\UnsignedShort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnsignedShortTest extends TestCase
{
    #[DataProvider('values')]
    public function testEncode(int $value, string $expected): void
    {
        $this->assertSame($expected, UnsignedShort::encode($value));
    }

    #[DataProvider('values')]
    public function testDecode(int $expected, string $bytes): void
    {
        $this->assertSame($expected, UnsignedShort::decode(new Buffer($bytes)));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function values(): array
    {
        return [
            'zero'           => [0,     "\x00\x00"],
            'minecraft port' => [25565, "\x63\xDD"],
            'max'            => [65535, "\xFF\xFF"],
        ];
    }
}
