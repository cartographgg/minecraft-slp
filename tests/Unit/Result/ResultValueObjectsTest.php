<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Result;

use Cartograph\SLP\Result\Description;
use Cartograph\SLP\Result\Players;
use Cartograph\SLP\Result\Sample;
use Cartograph\SLP\Result\Version;
use PHPUnit\Framework\TestCase;

final class ResultValueObjectsTest extends TestCase
{
    public function testVersionHoldsNameAndProtocol(): void
    {
        $v = new Version('1.21.1', 763);
        $this->assertSame('1.21.1', $v->name);
        $this->assertSame(763, $v->protocol);
    }

    public function testSampleHoldsNameAndUuid(): void
    {
        $s = new Sample('Notch', '069a79f4-44e9-4726-a5be-fca90e38aaf5');
        $this->assertSame('Notch', $s->name);
        $this->assertSame('069a79f4-44e9-4726-a5be-fca90e38aaf5', $s->uuid);
    }

    public function testPlayersHoldsCountsAndSampleList(): void
    {
        $samples = [new Sample('a', 'uuid-a'), new Sample('b', 'uuid-b')];
        $p       = new Players(online: 12, max: 100, sample: $samples);

        $this->assertSame(12, $p->online);
        $this->assertSame(100, $p->max);
        $this->assertSame($samples, $p->sample);
    }

    public function testDescriptionPlainTextOnly(): void
    {
        $d = new Description(raw: ['text' => 'A Minecraft Server'], plainText: 'A Minecraft Server');

        $this->assertSame('A Minecraft Server', $d->plainText);
        $this->assertSame(['text' => 'A Minecraft Server'], $d->raw);
    }

    public function testDescriptionWithComponentTree(): void
    {
        $tree = ['text' => 'Hello ', 'extra' => [['text' => 'World', 'color' => 'red']]];
        $d    = new Description(raw: $tree, plainText: 'Hello World');

        $this->assertSame($tree, $d->raw);
        $this->assertSame('Hello World', $d->plainText);
    }
}
