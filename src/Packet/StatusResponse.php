<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

use Cartograph\SLP\Codec\Buffer;
use Cartograph\SLP\Codec\McString;

/**
 * Server -> client: server status as a JSON document.
 *
 * Decoding into `PingResult`/`ForgePingResult` lives in `StatusDecoder` rather than here, because
 * the JSON shape varies across Minecraft versions and Forge variants and the packet itself is
 * just a transport for the payload.
 */
final readonly class StatusResponse implements Packet
{
    /**
     * Wire ID `0x00` (in the status state). Same numeric value as `StatusRequest`; direction distinguishes them.
     */
    public static function packetId(): int
    {
        return 0x00;
    }

    public static function decode(string $payload): static
    {
        return new self(json: McString::decode(new Buffer($payload)));
    }

    /**
     * @param string $json the server's status document, undecoded; pass to `StatusDecoder` to parse
     */
    public function __construct(public string $json)
    {
    }

    public function encode(): string
    {
        return McString::encode($this->json);
    }
}
