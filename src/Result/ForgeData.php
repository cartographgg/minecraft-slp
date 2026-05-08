<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * Forge mod/channel information extracted from the status JSON.
 *
 * Both the 1.7-1.12 (`modinfo`) and 1.13+ (`forgeData`) shapes normalise to this representation,
 * so consumers don't have to branch on Minecraft version. `fmlNetworkVersion` is 0 for the legacy
 * shape, which doesn't carry that field.
 */
final readonly class ForgeData
{
    /**
     * @param int                                                        $fmlNetworkVersion FML network version (0 for the legacy `modinfo` shape)
     * @param list<array{modId: string, version: string}>                $mods              installed mods, normalised
     * @param list<array{name: string, version: string, required: bool}> $channels          declared channels (always `[]` for the legacy `modinfo` shape)
     */
    public function __construct(
        public int $fmlNetworkVersion,
        public array $mods,
        public array $channels,
    ) {
    }
}
