<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\Exception\MalformedJsonException;
use Cartograph\SLP\JsonStatusDecoder;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Result\Sample;
use PHPUnit\Framework\TestCase;

final class JsonStatusDecoderVanillaTest extends TestCase
{
    public function testDecodesVanillaResponse(): void
    {
        $json = json_encode([
            'version' => ['name' => '1.21.1', 'protocol' => 763],
            'players' => [
                'online' => 5,
                'max'    => 100,
                'sample' => [
                    ['name' => 'Notch', 'id' => '069a79f4-44e9-4726-a5be-fca90e38aaf5'],
                ],
            ],
            'description' => ['text' => 'A Minecraft Server'],
            'favicon'     => 'data:image/png;base64,iVBOR...',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: 42);

        $this->assertInstanceOf(PingResult::class, $result);
        $this->assertSame('1.21.1', $result->version->name);
        $this->assertSame(763, $result->version->protocol);
        $this->assertSame(5, $result->players->online);
        $this->assertSame(100, $result->players->max);
        $this->assertCount(1, $result->players->sample);
        $this->assertInstanceOf(Sample::class, $result->players->sample[0]);
        $this->assertSame('Notch', $result->players->sample[0]->name);
        $this->assertSame('069a79f4-44e9-4726-a5be-fca90e38aaf5', $result->players->sample[0]->uuid);
        $this->assertSame('A Minecraft Server', $result->description->plainText);
        $this->assertSame('data:image/png;base64,iVBOR...', $result->favicon);
        $this->assertSame(42, $result->latencyMs);
        $this->assertSame([], $result->extras);
    }

    public function testStringDescriptionFallsThrough(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => 'Hello, world',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Hello, world', $result->description->plainText);
        $this->assertSame('Hello, world', $result->description->raw);
    }

    public function testComponentTreeWithExtraIsFlattenedToPlainText(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => [
                'text'  => 'Hello ',
                'extra' => [
                    ['text' => 'World', 'color' => 'red'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame('Hello World', $result->description->plainText);
    }

    public function testUnknownKeysLandInExtras(): void
    {
        $json = json_encode([
            'version'            => ['name' => '1.20', 'protocol' => 763],
            'players'            => ['online' => 0, 'max' => 0],
            'description'        => '',
            'enforcesSecureChat' => true,
            'previewsChat'       => false,
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame(['enforcesSecureChat' => true, 'previewsChat' => false], $result->extras);
    }

    public function testMalformedJsonThrows(): void
    {
        $this->expectException(MalformedJsonException::class);
        new JsonStatusDecoder()->decode('not json {{', latencyMs: null);
    }

    public function testMissingPlayersSampleDefaultsToEmptyList(): void
    {
        $json = json_encode([
            'version'     => ['name' => '1.20', 'protocol' => 763],
            'players'     => ['online' => 0, 'max' => 0],
            'description' => '',
        ], JSON_THROW_ON_ERROR);

        $result = new JsonStatusDecoder()->decode($json, latencyMs: null);

        $this->assertSame([], $result->players->sample);
    }
}
