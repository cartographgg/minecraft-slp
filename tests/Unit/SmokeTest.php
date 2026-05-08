<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testPhpunitWiringWorks(): void
    {
        $this->assertSame(1, 1);
    }
}
