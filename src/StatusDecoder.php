<?php

declare(strict_types=1);

namespace Cartograph\SLP;

use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Result\PingResult;

/**
 * Decodes the JSON document carried inside a `StatusResponse` packet.
 *
 * Production uses `JsonStatusDecoder`. Custom implementations can wrap the default to add logging
 * or metrics, swap in a stricter validator, or feed mocked decoded results in tests.
 */
interface StatusDecoder
{
    /**
     * Decode `$json` into a `PingResult` (or `ForgePingResult` if Forge data is present), attaching
     * `$latencyMs` to the result.
     *
     * @param string   $json      raw JSON document from the server's `StatusResponse`
     * @param int|null $latencyMs round-trip latency in milliseconds, or `null` if measurement was skipped
     *
     * @throws MalformedJsonException if `$json` does not parse as a JSON object
     */
    public function decode(string $json, ?int $latencyMs): PingResult|ForgePingResult;
}
