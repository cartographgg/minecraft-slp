<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\JsonStatusDecoder;
use PHPUnit\Framework\TestCase;

final class JsonStatusDecoderEdgeCasesTest extends TestCase
{
    public function testVersionNotAnArrayDefaultsToEmptyValues(): void
    {
        $json = json_encode([
            'version'     => 'unexpected-string',
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('', $result->version->name);
        $this->assertSame(0, $result->version->protocol);
    }

    public function testPlayersNotAnArrayDefaultsToZeroes(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21', 'protocol' => 763],
            'players'     => 'unexpected-string',
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame(0, $result->players->online);
        $this->assertSame(0, $result->players->max);
        $this->assertSame([], $result->players->sample);
    }

    public function testPlayersOnlineAndMaxNonIntDefaultToZero(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21', 'protocol' => 763],
            'players'     => ['online' => 'five', 'max' => null],
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame(0, $result->players->online);
        $this->assertSame(0, $result->players->max);
    }

    public function testMalformedSampleEntryIsSkipped(): void
    {
        $json = json_encode([
            'version' => ['name' => '1.21', 'protocol' => 763],
            'players' => [
                'online' => 1,
                'max'    => 100,
                'sample' => [
                    'not-an-array',
                    ['name' => 'NoId'],                         // missing id
                    ['id'   => 'NoName-uuid'],                    // missing name
                    ['name' => 123, 'id' => 'wrong-name-type'], // wrong types
                    ['name' => 'Notch', 'id' => 'valid-uuid'],  // valid
                ],
            ],
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertCount(1, $result->players->sample);
        $this->assertSame('Notch', $result->players->sample[0]->name);
    }

    public function testDescriptionWithUnexpectedTypeFallsBackToEmpty(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => 12345, // int, neither string nor array
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('', $result->description->raw);
        $this->assertSame('', $result->description->plainText);
    }

    public function testFlattenComponentTreeHandlesStringChild(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => [
                'text'  => 'Hello ',
                'extra' => ['World'], // string child instead of array
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Hello World', $result->description->plainText);
    }

    public function testFlattenComponentTreeIgnoresInvalidChild(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => [
                'text'  => 'Hello',
                'extra' => [12345], // int child, ignored
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Hello', $result->description->plainText);
    }

    public function testLegacyStringDescriptionStripsFormattingCodes(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '§aA §bMinecraft §r§lServer',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('§aA §bMinecraft §r§lServer', $result->description->raw);
        $this->assertSame('A Minecraft Server', $result->description->plainText);
    }

    public function testComponentTreeStripsFormattingCodesFromTextNodes(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => [
                'text'  => '§aHello ',
                'extra' => [
                    ['text' => '§bWorld'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Hello World', $result->description->plainText);
    }

    public function testStripsBungeeCordHexFormattingCodes(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '§x§F§F§0§0§0§0Red§r Normal',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Red Normal', $result->description->plainText);
    }

    public function testStripsAllFormattingCodeTypes(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '§0§1§2§3§4§5§6§7§8§9§a§b§c§d§e§f§k§l§m§n§o§rClean',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Clean', $result->description->plainText);
    }

    public function testPlainDescriptionWithoutFormattingCodesIsUnchanged(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => 'Just a plain server',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Just a plain server', $result->description->plainText);
        $this->assertSame('Just a plain server', $result->description->raw);
    }

    public function testForgeDataMalformedModEntriesAreSkipped(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'forgeData'   => [
                'fmlNetworkVersion' => 3,
                'mods'              => [
                    'not-an-array',
                    ['no-modId-key' => 'x'],
                    ['modId'        => 123],                                      // wrong type
                    ['modId'        => 'forge', 'modmarker' => '47.2.0'],
                ],
                'channels' => [
                    'not-an-array',
                    ['no-res-key' => 'x'],
                    ['res'        => 'fml:hs', 'version' => '1.0', 'required' => true],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertCount(1, $result->forgeData->mods);
        $this->assertSame('forge', $result->forgeData->mods[0]['modId']);
        $this->assertCount(1, $result->forgeData->channels);
    }

    public function testForgeDataModEntryWithoutModmarkerFallsBackToVersion(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'forgeData'   => [
                'fmlNetworkVersion' => 3,
                'mods'              => [
                    ['modId' => 'forge', 'version' => '47.2.0'], // version, no modmarker
                ],
                'channels' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('47.2.0', $result->forgeData->mods[0]['version']);
    }

    public function testForgeDataMissingFmlNetworkVersionDefaultsToZero(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'forgeData'   => [
                'mods'     => [],
                'channels' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame(0, $result->forgeData->fmlNetworkVersion);
    }

    public function testModInfoMalformedEntriesAreSkipped(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.10', 'protocol' => 210],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'modinfo'     => [
                'type'    => 'FML',
                'modList' => [
                    'not-an-array',
                    ['no-modid' => 'x'],
                    ['modid'    => 'forge'],            // missing version
                    ['modid'    => 'jei', 'version' => '15.0'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertCount(2, $result->forgeData->mods);
        $this->assertSame('forge', $result->forgeData->mods[0]['modId']);
        $this->assertSame('', $result->forgeData->mods[0]['version']);
        $this->assertSame('jei', $result->forgeData->mods[1]['modId']);
        $this->assertSame('15.0', $result->forgeData->mods[1]['version']);
    }
}
