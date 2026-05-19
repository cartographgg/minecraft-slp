<?php

declare(strict_types=1);

namespace Cartograph\SLP;

use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\Result\Description;
use Cartograph\SLP\Result\ForgeData;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Result\Players;
use Cartograph\SLP\Result\Sample;
use Cartograph\SLP\Result\Version;
use JsonException;

/**
 * Default `StatusDecoder`: parses the status JSON returned in a `StatusResponse` packet.
 *
 * Tolerant of upstream schema drift: top-level keys the decoder doesn't recognise survive in
 * `PingResult::$extras`, and unknown nested shapes are skipped rather than throwing. Sniffs for
 * Forge data (1.13+ `forgeData` and 1.7-1.12 `modinfo`) and returns `ForgePingResult` when
 * either is present.
 */
final class JsonStatusDecoder implements StatusDecoder
{
    /**
     * Decode the status JSON into a `PingResult` (or `ForgePingResult` if Forge data is present).
     *
     * @throws MalformedJsonException if `$json` does not parse as a JSON object
     */
    public function decode(string $json, ?int $latencyMs): PingResult|ForgePingResult
    {
        $decoded = $this->jsonDecode($json);

        $version     = $this->buildVersion($decoded);
        $players     = $this->buildPlayers($decoded);
        $description = $this->buildDescription($decoded);
        $favicon     = isset($decoded['favicon']) && is_string($decoded['favicon'])
            ? $decoded['favicon']
            : null;

        $forgeData = $this->extractForgeData($decoded);

        unset($decoded['version'], $decoded['players'], $decoded['description'], $decoded['favicon']);

        $base = new PingResult(
            version: $version,
            players: $players,
            description: $description,
            favicon: $favicon,
            latencyMs: $latencyMs,
            extras: $decoded,
        );

        if ($forgeData !== null) {
            return new ForgePingResult(base: $base, forgeData: $forgeData);
        }

        return $base;
    }

    /**
     * Sniff for Forge data. Recognises the 1.13+ `forgeData` shape and the 1.7-1.12 `modinfo` shape.
     * Mutates `$decoded` to remove the source key so it doesn't end up in `extras`.
     *
     * @param array<mixed, mixed> $decoded
     */
    private function extractForgeData(array &$decoded): ?ForgeData
    {
        if (isset($decoded['forgeData']) && is_array($decoded['forgeData'])) {
            $forgeData = $this->parseForgeData113($decoded['forgeData']);
            unset($decoded['forgeData']);

            return $forgeData;
        }

        if (isset($decoded['modinfo']) && is_array($decoded['modinfo'])) {
            $forgeData = $this->parseModInfo17($decoded['modinfo']);
            unset($decoded['modinfo']);

            return $forgeData;
        }

        return null;
    }

    /**
     * Parse the 1.13+ `forgeData` shape into normalised `ForgeData`. Skips malformed entries silently.
     *
     * @param array<mixed, mixed> $forge
     */
    private function parseForgeData113(array $forge): ForgeData
    {
        $mods = [];
        if (isset($forge['mods']) && is_array($forge['mods'])) {
            foreach ($forge['mods'] as $mod) {
                if (! is_array($mod) || ! isset($mod['modId']) || ! is_string($mod['modId'])) {
                    continue;
                }
                $version = '';
                if (isset($mod['modmarker']) && is_string($mod['modmarker'])) {
                    $version = $mod['modmarker'];
                } elseif (isset($mod['version']) && is_string($mod['version'])) {
                    $version = $mod['version'];
                }
                $mods[] = ['modId' => $mod['modId'], 'version' => $version];
            }
        }

        $channels = [];
        if (isset($forge['channels']) && is_array($forge['channels'])) {
            foreach ($forge['channels'] as $channel) {
                if (! is_array($channel) || ! isset($channel['res']) || ! is_string($channel['res'])) {
                    continue;
                }
                $channels[] = [
                    'name'     => $channel['res'],
                    'version'  => isset($channel['version'])  && is_string($channel['version']) ? $channel['version'] : '',
                    'required' => isset($channel['required']) && is_bool($channel['required']) ? $channel['required'] : false,
                ];
            }
        }

        return new ForgeData(
            fmlNetworkVersion: isset($forge['fmlNetworkVersion']) && is_int($forge['fmlNetworkVersion']) ? $forge['fmlNetworkVersion'] : 0,
            mods: $mods,
            channels: $channels,
        );
    }

    /**
     * Parse the 1.7-1.12 `modinfo` shape into normalised `ForgeData` (no channels, no FML version).
     *
     * @param array<mixed, mixed> $modinfo
     */
    private function parseModInfo17(array $modinfo): ForgeData
    {
        $mods = [];
        if (isset($modinfo['modList']) && is_array($modinfo['modList'])) {
            foreach ($modinfo['modList'] as $entry) {
                if (! is_array($entry) || ! isset($entry['modid']) || ! is_string($entry['modid'])) {
                    continue;
                }
                $mods[] = [
                    'modId'   => $entry['modid'],
                    'version' => isset($entry['version']) && is_string($entry['version']) ? $entry['version'] : '',
                ];
            }
        }

        return new ForgeData(fmlNetworkVersion: 0, mods: $mods, channels: []);
    }

    /**
     * Build a `Version` from the decoded JSON's `version` block. Defaults to empty/0 on missing fields.
     *
     * @param array<mixed, mixed> $decoded
     */
    private function buildVersion(array $decoded): Version
    {
        $v = $decoded['version'] ?? [];

        return new Version(
            name: is_array($v)     && isset($v['name']) && is_string($v['name']) ? $v['name'] : '',
            protocol: is_array($v) && isset($v['protocol']) && is_int($v['protocol']) ? $v['protocol'] : 0,
        );
    }

    /**
     * Build a `Players` from the decoded JSON's `players` block. Skips malformed sample entries.
     *
     * @param array<mixed, mixed> $decoded
     */
    private function buildPlayers(array $decoded): Players
    {
        $p      = $decoded['players'] ?? [];
        $online = is_array($p) && isset($p['online']) && is_int($p['online']) ? $p['online'] : 0;
        $max    = is_array($p) && isset($p['max']) && is_int($p['max']) ? $p['max'] : 0;
        $sample = [];

        if (is_array($p) && isset($p['sample']) && is_array($p['sample'])) {
            foreach ($p['sample'] as $entry) {
                if (
                    ! is_array($entry)
                    || ! isset($entry['name'], $entry['id'])
                    || ! is_string($entry['name'])
                    || ! is_string($entry['id'])
                ) {
                    continue;
                }
                $sample[] = new Sample(name: $entry['name'], uuid: $entry['id']);
            }
        }

        return new Players(online: $online, max: $max, sample: $sample);
    }

    /**
     * Build a `Description` from the decoded JSON's `description`, handling both string and component-tree forms.
     *
     * @param array<mixed, mixed> $decoded
     */
    private function buildDescription(array $decoded): Description
    {
        $raw = $decoded['description'] ?? '';

        if (is_string($raw)) {
            return new Description(raw: $raw, plainText: $this->stripFormattingCodes($raw));
        }

        if (is_array($raw)) {
            return new Description(raw: $raw, plainText: $this->stripFormattingCodes($this->flattenComponentTree($raw)));
        }

        return new Description(raw: '', plainText: '');
    }

    /**
     * Recursively flatten a Minecraft text-component tree into plain text.
     *
     * Concatenates `text` and any `extra` children (string or sub-tree) in document order.
     * Formatting attributes (color, bold, etc.) are intentionally dropped.
     *
     * @param array<mixed, mixed> $node
     */
    private function flattenComponentTree(array $node): string
    {
        $out = isset($node['text']) && is_string($node['text']) ? $node['text'] : '';

        if (isset($node['extra']) && is_array($node['extra'])) {
            foreach ($node['extra'] as $child) {
                if (is_string($child)) {
                    $out .= $child;
                } elseif (is_array($child)) {
                    $out .= $this->flattenComponentTree($child);
                }
            }
        }

        return $out;
    }

    /**
     * Strip Minecraft legacy formatting codes (§ followed by a character) from the given string.
     *
     * Handles BungeeCord hex sequences (§x§R§R§G§G§B§B) and standard single-character codes.
     */
    private function stripFormattingCodes(string $text): string
    {
        return preg_replace('/§x(?:§[0-9a-fA-F]){6}|§./u', '', $text) ?? $text;
    }

    /**
     * Decode a JSON string and return the top-level object as an associative array.
     *
     * @return array<mixed, mixed>
     *
     * @throws MalformedJsonException
     */
    private function jsonDecode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw MalformedJsonException::fromJsonError($e->getMessage(), $e);
        }

        if (! is_array($decoded)) {
            throw MalformedJsonException::fromJsonError('JSON was not a valid JSON object');
        }

        return $decoded;
    }
}
