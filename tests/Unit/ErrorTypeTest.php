<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\ErrorType;
use PHPUnit\Framework\TestCase;

final class ErrorTypeTest extends TestCase
{
    public function testCases(): void
    {
        $this->assertSame('dns', ErrorType::Dns->value);
        $this->assertSame('refused', ErrorType::Refused->value);
        $this->assertSame('timeout', ErrorType::Timeout->value);
        $this->assertSame('protocol_error', ErrorType::ProtocolError->value);
        $this->assertSame('other', ErrorType::Other->value);
    }
}
