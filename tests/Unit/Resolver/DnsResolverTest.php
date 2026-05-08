<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Resolver;

use Cartograph\SLP\Exception\DnsException;
use Cartograph\SLP\Resolver\DnsLookup;
use Cartograph\SLP\Resolver\DnsResolver;
use PHPUnit\Framework\TestCase;

final class DnsResolverTest extends TestCase
{
    public function testIpLiteralIsReturnedDirectly(): void
    {
        $resolver = new DnsResolver($this->makeLookup());
        $endpoint = $resolver->resolve('1.2.3.4');

        $this->assertSame('1.2.3.4', $endpoint->host);
        $this->assertSame(25565, $endpoint->port);
    }

    public function testIpLiteralWithExplicitPort(): void
    {
        $resolver = new DnsResolver($this->makeLookup());
        $endpoint = $resolver->resolve('1.2.3.4', port: 30000);

        $this->assertSame(30000, $endpoint->port);
    }

    public function testSrvLookupSucceeds(): void
    {
        $lookup = $this->makeLookup(
            srv: [
                ['target' => 'mc.example.com.', 'port' => 25500, 'pri' => 0],
            ],
            a: '10.0.0.1',
        );
        $resolver = new DnsResolver($lookup);
        $endpoint = $resolver->resolve('play.example.com');

        $this->assertSame('10.0.0.1', $endpoint->host);
        $this->assertSame(25500, $endpoint->port);
    }

    public function testSrvAbsentFallsThroughToARecordOnOriginalHost(): void
    {
        $lookup   = $this->makeLookup(srv: null, a: '10.0.0.1');
        $resolver = new DnsResolver($lookup);
        $endpoint = $resolver->resolve('play.example.com');

        $this->assertSame('10.0.0.1', $endpoint->host);
        $this->assertSame(25565, $endpoint->port);
    }

    public function testSrvPresentButARecordMissingThrows(): void
    {
        $lookup = $this->makeLookup(
            srv: [['target' => 'mc.example.com.', 'port' => 25500, 'pri' => 0]],
            a: null,
        );
        $resolver = new DnsResolver($lookup);

        $this->expectException(DnsException::class);
        $resolver->resolve('play.example.com');
    }

    public function testSrvTargetTrailingDotIsStrippedBeforeARecordLookup(): void
    {
        $capturedLookupName = null;
        $lookup             = new class($capturedLookupName) implements DnsLookup {
            public function __construct(public ?string &$captured)
            {
            }

            public function srv(string $name): ?array
            {
                return [['target' => 'mc.example.com.', 'port' => 25500, 'pri' => 0]];
            }

            public function a(string $name): ?string
            {
                $this->captured = $name;

                return '10.0.0.5';
            }
        };

        new DnsResolver($lookup)->resolve('play.example.com');

        $this->assertSame('mc.example.com', $capturedLookupName);
    }

    public function testSrvSortsByPriority(): void
    {
        $lookup = $this->makeLookup(
            srv: [
                ['target' => 'low.example.com.',  'port' => 25500, 'pri' => 10],
                ['target' => 'high.example.com.', 'port' => 25600, 'pri' => 0],
            ],
            a: '10.0.0.2',
        );
        $resolver = new DnsResolver($lookup);
        $endpoint = $resolver->resolve('play.example.com');

        $this->assertSame(25600, $endpoint->port); // chose lowest pri (highest priority)
    }

    /**
     * @param list<array{target: string, port: int, pri: int}>|null $srv
     */
    private function makeLookup(?array $srv = null, ?string $a = null): DnsLookup
    {
        return new class($srv, $a) implements DnsLookup {
            /**
             * @param list<array{target: string, port: int, pri: int}>|null $srv
             */
            public function __construct(private ?array $srv, private ?string $a)
            {
            }

            public function srv(string $name): ?array
            {
                return $this->srv;
            }

            public function a(string $name): ?string
            {
                return $this->a;
            }
        };
    }
}
