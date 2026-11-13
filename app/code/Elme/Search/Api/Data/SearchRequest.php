<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * One listing / search request.
 *
 * - query: search text; null or '' = a category listing.
 * - categoryId: only products in this category (anchor categories include their children).
 * - filters: facet code => selected values (OR inside one facet, AND between facets),
 *   e.g. ['colour_group' => ['Green', 'Blue'], 'in_stock' => ['1']].
 * - ranges: facet code => ['min' => ?float, 'max' => ?float], e.g. ['width' => ['min' => 50.0, 'max' => 120.0]].
 * - sort: one of the SORT_* constants; direction 'asc' | 'desc' or null for the default of that sort
 *   (relevance desc, position asc, name asc, price asc, newest desc).
 * - page: 1-based.
 */
final class SearchRequest
{
    public const SORT_RELEVANCE = 'relevance';
    public const SORT_POSITION = 'position';
    public const SORT_NAME = 'name';
    public const SORT_PRICE = 'price';
    public const SORT_NEWEST = 'newest';

    public const SORTS = [
        self::SORT_RELEVANCE,
        self::SORT_POSITION,
        self::SORT_NAME,
        self::SORT_PRICE,
        self::SORT_NEWEST,
    ];

    /**
     * @param array<string, string[]> $filters
     * @param array<string, array{min: ?float, max: ?float}> $ranges
     */
    public function __construct(
        public readonly int $storeId,
        public readonly ?string $query = null,
        public readonly ?int $categoryId = null,
        public readonly array $filters = [],
        public readonly array $ranges = [],
        public readonly string $sort = self::SORT_RELEVANCE,
        public readonly ?string $direction = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 24,
        public readonly bool $collapseFamilies = true
    ) {
    }

    public function hasQuery(): bool
    {
        return $this->query !== null && trim($this->query) !== '';
    }
}
