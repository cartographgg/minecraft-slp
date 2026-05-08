<?php

declare(strict_types=1);

namespace Cartograph\SLP;

/**
 * Sealed result of `Pinger::ping()` and `Pinger::execute()`.
 *
 * At runtime always either `Success` or `Failure`; callers should `match (true)` on `instanceof`
 * to discriminate. The class is `abstract readonly` so subclasses inherit the immutability
 * guarantee without needing to repeat the modifier.
 */
abstract readonly class PingOutcome
{
}
