<?php

declare(strict_types=1);

namespace Cartograph\SLP\Connection;

use Cartograph\SLP\Exception\ProtocolException;
use Cartograph\SLP\Exception\TransportException;
use Cartograph\SLP\Packet\Packet;

/**
 * A single Minecraft SLP packet stream.
 *
 * Implementations frame writes with a length prefix and packet ID, and read the same framing back,
 * narrowing the result to the requested packet type. Implemented by `BufferConnection` (in-memory,
 * for tests) and `StreamConnection` (over a real TCP socket).
 */
interface Connection
{
    /**
     * @throws TransportException if the underlying transport fails to deliver the bytes
     */
    public function send(Packet $packet): void;

    /**
     * @template T of Packet
     *
     * @param class-string<T> $type
     *
     * @return T
     *
     * @throws ProtocolException  if the framing is malformed or the packet ID does not match `$type`
     * @throws TransportException if the underlying transport fails or times out
     */
    public function receive(string $type): Packet;

    /**
     * Release the underlying transport. Idempotent on all implementations.
     */
    public function close(): void;
}
