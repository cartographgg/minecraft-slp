<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Result;

use Cartograph\SLP\Result\Description;
use Cartograph\SLP\Result\ForgeData;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Result\Players;
use Cartograph\SLP\Result\Version;
use PHPUnit\Framework\TestCase;

final class ForgePingResultTest extends TestCase
{
    public function testForgeDataHoldsModsAndChannels(): void
    {
        $data = new ForgeData(
            fmlNetworkVersion: 3,
            mods: [
                ['modId' => 'forge',     'version' => '1.20.1-47.2.0'],
                ['modId' => 'jei',       'version' => '15.2.0.27'],
            ],
            channels: [
                ['name' => 'fml:handshake', 'version' => '1.2.3.4', 'required' => true],
            ],
        );

        $this->assertSame(3, $data->fmlNetworkVersion);
        $this->assertCount(2, $data->mods);
        $this->assertSame('forge', $data->mods[0]['modId']);
        $this->assertCount(1, $data->channels);
    }

    public function testForgePingResultDecoratesBase(): void
    {
        $base = new PingResult(
            version: new Version('1.20.1', 763),
            players: new Players(0, 0, []),
            description: new Description('', ''),
            favicon: null,
            latencyMs: null,
            extras: [],
        );
        $forge = new ForgeData(fmlNetworkVersion: 3, mods: [], channels: []);

        $result = new ForgePingResult(base: $base, forgeData: $forge);

        $this->assertSame($base, $result->base);
        $this->assertSame($forge, $result->forgeData);
        $this->assertSame('1.20.1', $result->base->version->name);
    }
}
