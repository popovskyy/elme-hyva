<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * A product in autocomplete. No price: Elme prices are per customer and load separately.
 */
final class SuggestProduct
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $image = null
    ) {
    }
}
