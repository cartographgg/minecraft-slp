<?php

declare(strict_types=1);

namespace Cartograph\SLP\Result;

/**
 * Server MOTD (Message of the Day).
 *
 * `raw` preserves the original payload (a string for legacy servers, a component-tree array for
 * modern servers). `plainText` is the flattened version with formatting stripped, suitable for
 * display in contexts that don't render Minecraft component trees.
 */
final readonly class Description
{
    /**
     * @param array<mixed, mixed>|string $raw       the original `description` payload (string for legacy form, array for component-tree form)
     * @param string                     $plainText the flattened, formatting-stripped form
     */
    public function __construct(
        public array|string $raw,
        public string $plainText,
    ) {
    }
}
