<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Resolver;

use Cartograph\SLP\Resolver\Endpoint;
use PHPUnit\Framework\TestCase;

final class EndpointTest extends TestCase
{
    public function testHoldsHostAndPort(): void
    {
        $endpoint = new Endpoint('1.2.3.4', 25565);

        $this->assertSame('1.2.3.4', $endpoint->host);
        $this->assertSame(25565, $endpoint->port);
    }
}
