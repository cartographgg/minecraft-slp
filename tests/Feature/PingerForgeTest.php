<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Feature;

use Cartograph\SLP\Codec\LongInt;
use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\VarInt;
use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\Pinger;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use Cartograph\SLP\Result\ForgePingResult;
use Cartograph\SLP\Success;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class PingerForgeTest extends TestCase
{
    public function testForgeServerYieldsForgePingResult(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20.1-forge', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 20],
            'description' => 'Forge Server',
            'forgeData'   => [
                'fmlNetworkVersion' => 3,
                'mods'              => [
                    ['modId' => 'forge', 'modmarker' => '47.2.0'],
                    ['modId' => 'jei',   'modmarker' => '15.2.0'],
                ],
                'channels' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $statusInner = VarInt::encode(0x00) . McString::encode($json);
        $statusFrame = VarInt::encode(strlen($statusInner)) . $statusInner;
        $pongInner   = VarInt::encode(0x01) . LongInt::encode(0);
        $pongFrame   = VarInt::encode(strlen($pongInner)) . $pongInner;

        $conn     = new BufferConnection(preloaded: $statusFrame . $pongFrame);
        $resolver = new DnsResolver(new class implements DnsLookup {
            public function srv(string $name): ?array
            {
                return null;
            }

            public function a(string $name): ?string
            {
                return '10.0.0.6';
            }
        });
        $transport = new InMemoryTransport();
        $transport->register('10.0.0.6', 25565, $conn);

        $pinger  = new Pinger(resolver: $resolver, transport: $transport);
        $outcome = $pinger->ping('forge.example.com');

        $this->assertInstanceOf(Success::class, $outcome);
        $this->assertInstanceOf(ForgePingResult::class, $outcome->result);
        $this->assertSame(3, $outcome->result->forgeData->fmlNetworkVersion);
        $this->assertCount(2, $outcome->result->forgeData->mods);
        $this->assertSame('forge', $outcome->result->forgeData->mods[0]['modId']);
    }
}
