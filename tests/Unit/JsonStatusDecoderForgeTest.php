<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\JsonStatusDecoder;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Result\PingResult;
use PHPUnit\Framework\TestCase;

final class JsonStatusDecoderForgeTest extends TestCase
{
    public function testForgeData113PlusYieldsForgePingResult(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20.1', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'forgeData'   => [
                'fmlNetworkVersion' => 3,
                'mods'              => [
                    ['modId' => 'forge', 'modmarker' => '47.2.0'],
                    ['modId' => 'jei',   'modmarker' => '15.2.0'],
                ],
                'channels' => [
                    ['res' => 'fml:handshake', 'version' => '1.2.3.4', 'required' => true],
                ],
                'truncated' => false,
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertInstanceOf(ForgePingResult::class, $result);
        $this->assertSame(3, $result->forgeData->fmlNetworkVersion);
        $this->assertCount(2, $result->forgeData->mods);
        $this->assertSame('forge', $result->forgeData->mods[0]['modId']);
        $this->assertSame('47.2.0', $result->forgeData->mods[0]['version']);
        $this->assertCount(1, $result->forgeData->channels);
        $this->assertSame('fml:handshake', $result->forgeData->channels[0]['name']);

        $this->assertArrayNotHasKey('forgeData', $result->base->extras);
    }

    public function testModInfo17To112FormatYieldsForgePingResult(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.10.2', 'protocol' => 210],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'modinfo'     => [
                'type'    => 'FML',
                'modList' => [
                    ['modid' => 'forge',         'version' => '14.23.5'],
                    ['modid' => 'mantle',        'version' => '1.2.0'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertInstanceOf(ForgePingResult::class, $result);
        $this->assertSame(0, $result->forgeData->fmlNetworkVersion); // unknown for 1.7-1.12 form
        $this->assertCount(2, $result->forgeData->mods);
        $this->assertSame('forge', $result->forgeData->mods[0]['modId']);
        $this->assertSame('14.23.5', $result->forgeData->mods[0]['version']);
        $this->assertSame([], $result->forgeData->channels);

        $this->assertArrayNotHasKey('modinfo', $result->base->extras);
    }

    public function testForgeChannelWithNonBoolRequiredFallsBackToFalse(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20.1', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
            'forgeData'   => [
                'fmlNetworkVersion' => 3,
                'mods'              => [],
                'channels'          => [
                    ['res' => 'fml:handshake', 'version' => '1.2.3.4', 'required' => 'yes'],
                    ['res' => 'fml:loginwrapper', 'version' => '1.0.0.0'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertInstanceOf(ForgePingResult::class, $result);
        $this->assertCount(2, $result->forgeData->channels);
        $this->assertFalse($result->forgeData->channels[0]['required']);
        $this->assertFalse($result->forgeData->channels[1]['required']);
    }

    public function testNonForgeStaysAsPlainPingResult(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.21.1', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertInstanceOf(PingResult::class, $result);
        $this->assertNotInstanceOf(ForgePingResult::class, $result);
    }
}
