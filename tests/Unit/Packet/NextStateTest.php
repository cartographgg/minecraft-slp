<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Packet;

use Cartograph\SLP\Packet\NextState;
use PHPUnit\Framework\TestCase;

final class NextStateTest extends TestCase
{
    public function testStatusIsOne(): void
    {
        $this->assertSame(1, NextState::Status->value);
    }

    public function testLoginIsTwo(): void
    {
        $this->assertSame(2, NextState::Login->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(NextState::Status, NextState::from(1));
        $this->assertSame(NextState::Login, NextState::from(2));
    }
}
