<?php

declare(strict_types=1);

namespace Cartograph\SLP\Codec;

use Cartograph\SLP\Exception\ProtocolException;

/**
 * Mutable byte buffer with an internal read cursor.
 *
 * Used by codecs that consume bytes incrementally; `read()` advances the cursor and throws when
 * the buffer is too short, while `write()` appends without moving the cursor.
 */
final class Buffer
{
    /**
     * Read cursor position. Advanced by `read()`, never moved by `write()`.
     */
    private int $offset = 0;

    /**
     * @param string $data initial byte contents; may be appended to via `write()`
     */
    public function __construct(private string $data = '')
    {
    }

    /**
     * Consume `$bytes` bytes from the cursor and advance it.
     *
     * @throws ProtocolException if the buffer has fewer than `$bytes` bytes remaining
     */
    public function read(int $bytes): string
    {
        $available = strlen($this->data) - $this->offset;
        if ($bytes > $available) {
            throw ProtocolException::shortRead($bytes, $available);
        }
        $chunk = substr($this->data, $this->offset, $bytes);
        $this->offset += $bytes;
        return $chunk;
    }

    /**
     * Append bytes to the end of the buffer. Does not move the read cursor.
     */
    public function write(string $bytes): void
    {
        $this->data .= $bytes;
    }

    /**
     * Return the entire buffer contents (independent of the read cursor).
     */
    public function bytes(): string
    {
        return $this->data;
    }

    /**
     * Number of bytes still available to `read()` from the cursor onwards.
     */
    public function remaining(): int
    {
        return strlen($this->data) - $this->offset;
    }

    /**
     * `true` once the cursor has reached or passed the end of the data.
     */
    public function isAtEnd(): bool
    {
        return $this->offset >= strlen($this->data);
    }
}
