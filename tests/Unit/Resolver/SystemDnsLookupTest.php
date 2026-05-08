<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit\Resolver;

use Cartograph\SLP\Resolver\SystemDnsLookup;
use PHPUnit\Framework\TestCase;

final class SystemDnsLookupTest extends TestCase
{
    public function testARecordResolvesLocalhost(): void
    {
        $lookup = new SystemDnsLookup();
        $ip     = $lookup->a('localhost');

        // localhost should resolve to 127.0.0.1 on all test environments
        $this->assertSame('127.0.0.1', $ip);
    }

    public function testARecordReturnsNullForUnresolvableName(): void
    {
        $lookup = new SystemDnsLookup();
        // Use a hostname that reliably fails DNS resolution
        $result = $lookup->a('this-host-does-not-exist.invalid');

        $this->assertNull($result);
    }

    public function testSrvRecordReturnsNullWhenNoRecordsFound(): void
    {
        $lookup = new SystemDnsLookup();
        // localhost has no SRV records
        $result = $lookup->srv('_minecraft._tcp.localhost');

        $this->assertNull($result);
    }
}
