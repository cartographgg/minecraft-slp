<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * Decoded server status.
 *
 * `extras` holds any top-level JSON keys the decoder did not recognise, so server-specific
 * extensions survive the decode without forcing schema changes. `latencyMs` is `null` when the
 * optional Ping/Pong exchange was skipped or its echoed payload didn't match.
 */
final readonly class PingResult
{
    /**
     * @param Version             $version     server version block
     * @param Players             $players     player counts and sample
     * @param Description         $description MOTD (raw + plain-text)
     * @param string|null         $favicon     base64-encoded PNG data URI, or `null` if absent
     * @param int|null            $latencyMs   round-trip in milliseconds, or `null` if measurement failed
     * @param array<mixed, mixed> $extras      unrecognised top-level JSON keys (forward-compatibility escape hatch)
     */
    public function __construct(
        public Version $version,
        public Players $players,
        public Description $description,
        public ?string $favicon,
        public ?int $latencyMs,
        public array $extras,
    ) {
    }
}
