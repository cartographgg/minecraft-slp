<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\MonotonicClock;
use PHPUnit\Framework\TestCase;

final class MonotonicClockTest extends TestCase
{
    public function testReturnsMonotonicallyNonDecreasingMilliseconds(): void
    {
        $clock = new MonotonicClock();

        $first  = $clock->monotonicMs();
        $second = $clock->monotonicMs();

        $this->assertGreaterThanOrEqual($first, $second);
    }
}
