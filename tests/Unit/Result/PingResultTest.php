<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Result;

use Cartograph\SLP\Result\Description;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Result\Players;
use Cartograph\SLP\Result\Version;
use PHPUnit\Framework\TestCase;

final class PingResultTest extends TestCase
{
    public function testHoldsTypedFieldsAndExtras(): void
    {
        $result = new PingResult(
            version: new Version('1.21.1', 763),
            players: new Players(online: 5, max: 100, sample: []),
            description: new Description(raw: 'A Minecraft Server', plainText: 'A Minecraft Server'),
            favicon: 'data:image/png;base64,iVBOR...',
            latencyMs: 42,
            extras: ['enforcesSecureChat' => true],
        );

        $this->assertSame('1.21.1', $result->version->name);
        $this->assertSame(5, $result->players->online);
        $this->assertSame('A Minecraft Server', $result->description->plainText);
        $this->assertSame('data:image/png;base64,iVBOR...', $result->favicon);
        $this->assertSame(42, $result->latencyMs);
        $this->assertSame(['enforcesSecureChat' => true], $result->extras);
    }

    public function testFaviconAndLatencyAreOptional(): void
    {
        $result = new PingResult(
            version: new Version('1.21.1', 763),
            players: new Players(0, 0, []),
            description: new Description('', ''),
            favicon: null,
            latencyMs: null,
            extras: [],
        );

        $this->assertNull($result->favicon);
        $this->assertNull($result->latencyMs);
    }
}
