<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * One item of a result page. familyCode is set when the item stands for a collapsed family.
 */
final class SearchHit
{
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly ?string $familyCode = null
    ) {
    }
}
