<?php

declare(strict_types=1);

namespace Cartograph\SLP\Packet;

/**
 * State the client wants to transition into after the Handshake.
 *
 * SLP uses `Status`; `Login` is exposed for completeness (the wire protocol defines it) but is not
 * driven by this library.
 */
enum NextState: int
{
    /**
     * Asks the server for a `StatusResponse`. The only state this library drives.
     */
    case Status = 1;

    /**
     * Begins the login flow. Defined by the wire protocol but not used by this library.
     */
    case Login = 2;
}
