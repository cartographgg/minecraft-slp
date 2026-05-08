<?php

declare(strict_types=1);

namespace Cartograph\SLP;

use Throwable;

/**
 * `PingOutcome` for an attempt that surfaced an SLP-protocol error.
 *
 * `$type` is the coarse classification (DNS, refused, timeout, protocol error, other); `$previous`
 * carries the original exception when one was caught, or `null` for cases where no exception is
 * appropriate (none currently exist, but the field stays optional for forward compatibility).
 */
final readonly class Failure extends PingOutcome
{
    /**
     * @param ErrorType      $type     coarse classification of the failure
     * @param Throwable|null $previous original exception when one was caught, or `null`
     */
    public function __construct(
        public ErrorType $type,
        public ?Throwable $previous = null,
    ) {
    }
}
