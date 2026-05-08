<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * Player counts and a sample of currently online players.
 *
 * Servers may suppress the sample even when `online > 0` (some plugins do this for privacy or
 * anti-bot reasons), so an empty `sample` is not a reliable signal that the server is empty.
 */
final readonly class Players
{
    /**
     * @param int          $online currently online players, as the server reports them
     * @param int          $max    advertised maximum capacity
     * @param list<Sample> $sample subset of online players exposed by the server, possibly empty
     */
    public function __construct(
        public int $online,
        public int $max,
        public array $sample = [],
    ) {
    }
}
