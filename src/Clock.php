<?php

declare(strict_types=1);

namespace Cartograph\SLP;

/**
 * Monotonic millisecond clock.
 *
 * Seam over `hrtime()` so latency measurement in `Pinger::measurePing()` is testable. Production
 * uses `MonotonicClock`; tests substitute a fake that returns a predetermined sequence so latency
 * assertions can be exact instead of "is non-negative int".
 */
interface Clock
{
    /**
     * Return the current monotonic time in whole milliseconds.
     *
     * Successive calls must be non-decreasing. The returned value is opaque: it has no defined
     * relationship to wall-clock time and is only meaningful when subtracted from another return
     * value of the same `Clock` instance.
     */
    public function monotonicMs(): int;
}
