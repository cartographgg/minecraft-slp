<?php

declare(strict_types=1);

namespace Cartograph\SLP;

/**
 * Default `Clock`: wraps `hrtime(true)` and converts nanoseconds to milliseconds.
 *
 * Trivial adapter, excluded from mutation testing because the arithmetic mutations cannot be
 * killed without an end-to-end test against the system clock, which is out of scope.
 */
final class MonotonicClock implements Clock
{
    /**
     * Read `hrtime(true)` (nanoseconds since an unspecified origin) and convert to milliseconds.
     */
    public function monotonicMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }
}
