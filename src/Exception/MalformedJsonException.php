<?php

declare(strict_types=1);

namespace Cartograph\SLP\Exception;

use Throwable;

/**
 * Raised when the server's status response is not valid JSON.
 *
 * Surfaced from `JsonStatusDecoder::decode()`. Maps to `ErrorType::ProtocolError` once `Pinger::execute()`
 * catches it and converts the result to `Failure`.
 */
final class MalformedJsonException extends MinecraftSlpException
{
    /**
     * Build the exception with a "Status JSON did not parse: <message>" message.
     *
     * @param string         $message  the underlying parse error (e.g. from `json_last_error_msg()` or `JsonException::getMessage()`)
     * @param Throwable|null $previous original exception when one was caught
     */
    public static function fromJsonError(string $message, ?Throwable $previous = null): self
    {
        return new self(sprintf('Status JSON did not parse: %s', $message), previous: $previous);
    }
}
