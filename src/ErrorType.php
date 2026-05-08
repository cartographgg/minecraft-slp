<?php

declare(strict_types=1);

namespace Cartograph\SLP;

/**
 * Coarse classification of why an SLP attempt failed.
 *
 * Carried on `Failure` and on `TransportException`. `Refused` and `Timeout` distinguish at the
 * transport layer; `ProtocolError` covers framing and JSON parse failures; `Other` is the catch-all
 * when nothing more specific applies.
 */
enum ErrorType: string
{
    /**
     * Address could not be resolved to an IP. Carried on `DnsException`.
     */
    case Dns = 'dns';

    /**
     * Connect attempt was actively refused (kernel returned ECONNREFUSED).
     */
    case Refused = 'refused';

    /**
     * Connect or read exceeded the configured timeout window.
     */
    case Timeout = 'timeout';

    /**
     * SLP framing was malformed, packet IDs disagreed, or the status JSON failed to parse.
     */
    case ProtocolError = 'protocol_error';

    /**
     * Catch-all for failures that don't fit the more specific types (e.g. write failures, host
     * unreachable).
     */
    case Other = 'other';
}
