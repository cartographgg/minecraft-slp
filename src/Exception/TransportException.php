<?php

declare(strict_types=1);

namespace Cartograph\SLP\Exception;

use Cartograph\SLP\ErrorType;
use Throwable;

/**
 * Transport-level failure: connect refused, read timeout, or write failure.
 *
 * Carries its own `ErrorType` so `Pinger::execute()` can pass that classification through to the
 * resulting `Failure` without re-deriving it from the message text.
 */
final class TransportException extends MinecraftSlpException
{
    /**
     * Build the canonical connect-failure exception, classifying `$errno` into an `ErrorType`.
     *
     * `SOCKET_ECONNREFUSED` becomes `ErrorType::Refused`, `SOCKET_ETIMEDOUT` becomes
     * `ErrorType::Timeout`, anything else falls through to `ErrorType::Other`.
     */
    public static function connect(string $host, int $port, int $errno, string $errstr): self
    {
        $type = match ($errno) {
            SOCKET_ECONNREFUSED => ErrorType::Refused,
            SOCKET_ETIMEDOUT    => ErrorType::Timeout,
            default             => ErrorType::Other,
        };

        return new self("Connect to {$host}:{$port} failed: {$errstr} ({$errno})", $type);
    }

    /**
     * Build the canonical read-timeout exception with `ErrorType::Timeout`.
     */
    public static function timeout(): self
    {
        return new self('Read timed out', ErrorType::Timeout);
    }

    /**
     * @param string         $message  human-readable failure description
     * @param ErrorType      $type     coarse classification surfaced via `$exception->type`
     * @param Throwable|null $previous original exception, when this wraps another failure
     */
    public function __construct(
        string $message,
        public readonly ErrorType $type,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
