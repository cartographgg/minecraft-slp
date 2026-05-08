<?php

declare(strict_types=1);

namespace Cartograph\SLP\Exception;

use RuntimeException;

/**
 * Base for every exception this package raises.
 *
 * Subclasses are caught and converted to `Failure` inside `Pinger::execute()`; library callers can
 * catch this base to handle any SLP-protocol error in one place without enumerating each subclass.
 */
abstract class MinecraftSlpException extends RuntimeException
{
}
