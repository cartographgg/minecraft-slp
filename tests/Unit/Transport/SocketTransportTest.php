<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Transport;

use Cartograph\SLP\Connection\StreamConnection;
use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Packet\StatusResponse;
use Cartograph\SLP\Transport\SocketTransport;
use PHPUnit\Framework\TestCase;

final class SocketTransportTest extends TestCase
{
    public function testConnectToLocalListenerReturnsStreamConnection(): void
    {
        // Open a real TCP listener on a random local port for this test
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        $this->assertNotFalse($name);
        [, $port] = explode(':', $name);

        $transport = new SocketTransport();
        $conn      = $transport->connect('127.0.0.1', (int) $port, 1.0);

        $this->assertInstanceOf(StreamConnection::class, $conn);
        $conn->close();
        fclose($server);
    }

    public function testStreamReadHonoursFractionalTimeoutOnReturnedConnection(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        $this->assertNotFalse($name);
        [, $port] = explode(':', $name);

        $transport = new SocketTransport();
        $conn      = $transport->connect('127.0.0.1', (int) $port, 1.5);

        $start = hrtime(true);
        try {
            $conn->receive(StatusResponse::class);
            $this->fail('expected TransportException');
        } catch (TransportException $e) {
            $elapsedSeconds = (hrtime(true) - $start) / 1_000_000_000;

            $this->assertSame(ErrorType::Timeout, $e->type);
            $this->assertGreaterThan(1.3, $elapsedSeconds, 'fread returned too soon: micro portion of timeout was wrong or set_timeout was skipped');
            $this->assertLessThan(2.0, $elapsedSeconds, 'fread blocked too long: micro portion of timeout was inflated or set_timeout was skipped');
        } finally {
            $conn->close();
            fclose($server);
        }
    }

    public function testConnectToClosedPortRaisesRefused(): void
    {
        // Bind an ephemeral port then close it so the port is unbound.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server);
        $name = stream_socket_get_name($server, false);
        $this->assertNotFalse($name);
        [, $port] = explode(':', $name);
        fclose($server);

        $transport = new SocketTransport();

        try {
            $transport->connect('127.0.0.1', (int) $port, 1.0);
            $this->fail('expected TransportException');
        } catch (TransportException $e) {
            $this->assertContains(
                $e->type,
                [ErrorType::Refused, ErrorType::Other], // some kernels report differently
            );
        }
    }
}
