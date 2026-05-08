<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * One entry in `Players::$sample`.
 *
 * `uuid` is the player's Minecraft UUID as a string. The library doesn't normalise dash placement;
 * it is whatever the server emitted.
 */
final readonly class Sample
{
    /**
     * @param string $name player display name as the server reports it
     * @param string $uuid Minecraft UUID, in whatever dash placement the server emitted
     */
    public function __construct(
        public string $name,
        public string $uuid,
    ) {
    }
}
