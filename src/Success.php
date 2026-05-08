<?php

declare(strict_types=1);

namespace Cartograph\SLP;

/**
 * `PingOutcome` for a successful flow.
 *
 * `$result` is whatever the closure returned for `execute()`, or a `PingResult|ForgePingResult` for
 * the standard `ping()`. The `@template T` ties `$result`'s static type to the closure's return type
 * so callers don't lose narrow typing through `Success`.
 *
 * @template T
 */
final readonly class Success extends PingOutcome
{
    /**
     * @param T $result
     */
    public function __construct(public mixed $result)
    {
    }
}
