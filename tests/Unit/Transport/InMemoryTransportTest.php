<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Transport;

use Cartograph\SLP\Connection\BufferConnection;
use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class InMemoryTransportTest extends TestCase
{
    public function testReturnsPreloadedConnectionForKnownEndpoint(): void
    {
        $connection = new BufferConnection(preloaded: 'preloaded-bytes');
        $transport  = new InMemoryTransport();
        $transport->register('1.2.3.4', 25565, $connection);

        $this->assertSame($connection, $transport->connect('1.2.3.4', 25565));
    }

    public function testThrowsRefusedWhenEndpointHasNoRegisteredConnection(): void
    {
        $transport = new InMemoryTransport();

        try {
            $transport->connect('1.2.3.4', 25565);
            $this->fail('expected TransportException');
        } catch (TransportException $e) {
            $this->assertSame(ErrorType::Refused, $e->type);
        }
    }
}
