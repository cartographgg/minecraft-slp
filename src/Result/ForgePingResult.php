<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * `PingResult` augmented with Forge-specific mod/channel data.
 *
 * Returned in place of `PingResult` whenever the status JSON includes a `forgeData` (1.13+) or
 * `modinfo` (1.7-1.12) block. Wraps the underlying `PingResult` rather than extending it so callers
 * can match on instance to discriminate vanilla vs. Forge servers.
 */
final readonly class ForgePingResult
{
    /**
     * @param PingResult $base      the underlying decoded status (vanilla fields)
     * @param ForgeData  $forgeData Forge-specific mod and channel info
     */
    public function __construct(
        public PingResult $base,
        public ForgeData $forgeData,
    ) {
    }
}
