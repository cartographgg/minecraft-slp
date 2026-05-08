<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\ExecutionContext;
use Cartograph\SLP\Resolver\Endpoint;
use PHPUnit\Framework\TestCase;

final class ExecutionContextTest extends TestCase
{
    public function testHoldsAllSixFields(): void
    {
        $connection = new BufferConnection();
        $endpoint   = new Endpoint('10.0.0.1', 25565);

        $context = new ExecutionContext(
            connection: $connection,
            endpoint: $endpoint,
            address: 'play.example.com',
            port: null,
            protocolVersion: 763,
            timeout: 3.0,
        );

        $this->assertSame($connection, $context->connection);
        $this->assertSame($endpoint, $context->endpoint);
        $this->assertSame('play.example.com', $context->address);
        $this->assertNull($context->port);
        $this->assertSame(763, $context->protocolVersion);
        $this->assertSame(3.0, $context->timeout);
    }

    public function testPortCanBeExplicit(): void
    {
        $connection = new BufferConnection();
        $endpoint   = new Endpoint('10.0.0.1', 30000);

        $context = new ExecutionContext(
            connection: $connection,
            endpoint: $endpoint,
            address: '10.0.0.1',
            port: 30000,
            protocolVersion: -1,
            timeout: 3.0,
        );

        $this->assertSame(30000, $context->port);
    }
}
