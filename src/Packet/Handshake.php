<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\McString;
use Cartograph\SLP\Codec\UnsignedShort;
use Cartograph\SLP\Codec\VarInt;

/**
 * Client -> server: first packet of any SLP exchange.
 *
 * `serverAddress` and `serverPort` echo what the client believes it is connecting to. Ownership-
 * verification flows ride custom values in `serverAddress` (e.g. `verify-{token}.host`) so the
 * server's logs reveal whether a probe is the legitimate operator.
 */
final readonly class Handshake implements Packet
{
    /**
     * Wire ID `0x00` (in the handshaking state).
     */
    public static function packetId(): int
    {
        return 0x00;
    }

    public static function decode(string $payload): static
    {
        $buffer = new Buffer($payload);
        return new self(
            protocolVersion: VarInt::decode($buffer),
            serverAddress: McString::decode($buffer),
            serverPort: UnsignedShort::decode($buffer),
            nextState: NextState::from(VarInt::decode($buffer)),
        );
    }

    /**
     * @param int       $protocolVersion Minecraft protocol number; `-1` is the conventional "no preference" sentinel
     * @param string    $serverAddress   what the client claims it is connecting to (echoed in server logs)
     * @param int       $serverPort      what the client claims it is connecting to
     * @param NextState $nextState       always `Status` for SLP flows
     */
    public function __construct(
        public int $protocolVersion,
        public string $serverAddress,
        public int $serverPort,
        public NextState $nextState,
    ) {
    }

    public function encode(): string
    {
        return VarInt::encode($this->protocolVersion)
             . McString::encode($this->serverAddress)
             . UnsignedShort::encode($this->serverPort)
             . VarInt::encode($this->nextState->value);
    }
}
