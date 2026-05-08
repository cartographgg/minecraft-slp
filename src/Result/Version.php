<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * Server version block.
 *
 * `name` is the human label (e.g. "1.21.1"); `protocol` is the wire protocol number used during
 * Handshake negotiation. The two can drift when servers expose a custom `name` for branding while
 * running a standard `protocol`.
 */
final readonly class Version
{
    /**
     * @param string $name     human label (e.g. "1.21.1"), possibly customised by the server
     * @param int    $protocol wire protocol number used in Handshake negotiation
     */
    public function __construct(
        public string $name,
        public int $protocol,
    ) {
    }
}
