<?php

declare(strict_types=1);

namespace Cartograph\SLP\Tests\Unit;

use Cartograph\SLP\ErrorType;
use Cartograph\SLP\Failure;
use Cartograph\SLP\PingOutcome;
use Cartograph\SLP\Result\Description;
use Cartograph\SLP\Result\PingResult;
use Cartograph\SLP\Result\Players;
use Cartograph\SLP\Result\Version;
use Cartograph\SLP\Success;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PingOutcomeTest extends TestCase
{
    public function testSuccessHoldsResult(): void
    {
        $result = new PingResult(
            version: new Version('1.21.1', 763),
            players: new Players(0, 0, []),
            description: new Description('', ''),
            favicon: null,
            latencyMs: 42,
            extras: [],
        );

        $outcome = new Success($result);

        $this->assertInstanceOf(PingOutcome::class, $outcome);
        $this->assertSame($result, $outcome->result);
    }

    public function testFailureHoldsTypeAndOptionalPrevious(): void
    {
        $previous = new RuntimeException('underlying');
        $outcome  = new Failure(ErrorType::Timeout, $previous);

        $this->assertInstanceOf(PingOutcome::class, $outcome);
        $this->assertSame(ErrorType::Timeout, $outcome->type);
        $this->assertSame($previous, $outcome->previous);
    }

    public function testFailurePreviousIsOptional(): void
    {
        $outcome = new Failure(ErrorType::Other);
        $this->assertNull($outcome->previous);
    }
}
