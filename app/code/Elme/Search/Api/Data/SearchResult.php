<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * One page of results in order, the total number of items (families collapsed) and the facets.
 */
final class SearchResult
{
    /**
     * @param SearchHit[] $hits
     * @param Facet[] $facets
     */
    public function __construct(
        public readonly array $hits,
        public readonly int $total,
        public readonly array $facets = []
    ) {
    }

    /**
     * @return int[]
     */
    public function getProductIds(): array
    {
        return array_map(static fn (SearchHit $hit): int => $hit->productId, $this->hits);
    }

    /**
     * @return string[]
     */
    public function getSkus(): array
    {
        return array_map(static fn (SearchHit $hit): string => $hit->sku, $this->hits);
    }

    public function getFacet(string $code): ?Facet
    {
        foreach ($this->facets as $facet) {
            if ($facet->code === $code) {
                return $facet;
            }
        }
        return null;
    }
}
