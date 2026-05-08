<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Codec;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\McString;
use PHPUnit\Framework\TestCase;

final class McStringTest extends TestCase
{
    public function testEncodeWritesVarIntLengthThenBytes(): void
    {
        $this->assertSame("\x05hello", McString::encode('hello'));
    }

    public function testEncodeEmptyString(): void
    {
        $this->assertSame("\x00", McString::encode(''));
    }

    public function testEncodeMultiByteUtf8(): void
    {
        $emoji = "\xF0\x9F\x98\x80";
        $this->assertSame("\x04{$emoji}", McString::encode($emoji));
    }

    public function testDecodeReadsLengthThenBytes(): void
    {
        $buffer = new Buffer("\x05hello\xAA");
        $this->assertSame('hello', McString::decode($buffer));
        $this->assertSame("\xAA", $buffer->read(1));
    }

    public function testRoundTripWithLongerString(): void
    {
        $value = str_repeat('x', 200);
        $bytes = McString::encode($value);
        $this->assertSame($value, McString::decode(new Buffer($bytes)));
    }
}
