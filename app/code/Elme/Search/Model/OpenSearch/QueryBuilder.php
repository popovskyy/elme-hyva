<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Facet\FacetDefinition;
use Elme\Search\Model\SkuQuery;

/**
 * SearchRequest -> OpenSearch request body. Pure: no I/O, no Magento state.
 *
 * - Relevance: exact SKU, then SKU prefix, then name phrase, then name / type / colour / material words.
 *   Typo tolerance only for longer words (AUTO:5,8 = none up to 4 letters, so "vase" never matches "Vale").
 * - Families collapse on family_key (family_code or the product's own key); total and facet counts are the
 *   number of distinct family_key values, so they count tiles, not members.
 * - Disjunctive facets: selected facet values go into post_filter; each facet aggregation sees the filters of
 *   the other facets only, so its own options keep their counts while one is selected.
 */
class QueryBuilder
{
    public const COLLAPSE_FIELD = 'family_key';
    public const AGG_TOTAL = '_total';
    public const AGG_PREFIX = 'facet_';
    public const AGG_ITEMS = 'items';

    public const VISIBILITY_CATALOG = [2, 4];
    public const VISIBILITY_SEARCH = [3, 4];

    /**
     * Fields of the word query, with boosts. What the item is (product type, type) outranks a word in the name, so
     * "vase" lists vases and vessels before "Canvas Wall Art Vases".
     */
    public const TEXT_FIELDS = [
        'product_type.text^6',
        'type_detail.text^5',
        'name^4',
        'colour_group.text^2',
        'color.text^2',
        'material.text',
        'style.text',
        'features.text',
    ];

    public const BOOST_SKU_EXACT = 1000;
    public const BOOST_SKU_COMPACT = 800;
    public const BOOST_SKU_PREFIX = 200;
    public const BOOST_NAME_PHRASE = 20;

    /**
     * @param FacetDefinition[] $facets
     */
    public function build(SearchRequest $request, array $facets): array
    {
        $collapse = $request->collapseFamilies;
        $facetFilters = $this->buildFacetFilters($request, $facets);

        $body = [
            'from' => max(0, ($request->page - 1) * $request->pageSize),
            'size' => max(0, $request->pageSize),
            'track_total_hits' => true,
            '_source' => ['id', 'sku', 'family_code'],
            'query' => $this->buildBaseQuery($request),
            'sort' => $this->buildSort($request),
        ];
        if ($facetFilters !== []) {
            $body['post_filter'] = ['bool' => ['filter' => array_values($facetFilters)]];
        }
        if ($collapse) {
            $body['collapse'] = ['field' => self::COLLAPSE_FIELD];
        }

        $aggs = [];
        if ($collapse) {
            $aggs[self::AGG_TOTAL] = [
                'filter' => $this->combine(array_values($facetFilters)),
                'aggs' => [self::AGG_ITEMS => $this->cardinality()],
            ];
        }
        foreach ($facets as $facet) {
            $others = array_values(array_diff_key($facetFilters, [$facet->code => true]));
            $aggs[self::AGG_PREFIX . $facet->code] = [
                'filter' => $this->combine($others),
                'aggs' => ['values' => $this->buildFacetAgg($facet, $collapse)],
            ];
        }
        if ($aggs !== []) {
            $body['aggs'] = $aggs;
        }
        return $body;
    }

    /**
     * Autocomplete: best products (families collapsed), the categories they sit in, their types for query
     * suggestions and a spelling suggestion.
     */
    public function buildSuggest(string $query, int $limit): array
    {
        $request = new SearchRequest(0, $query, null, [], [], SearchRequest::SORT_RELEVANCE);
        $body = [
            'size' => max(1, $limit),
            '_source' => ['id', 'sku', 'name', 'url', 'image'],
            'query' => $this->buildBaseQuery($request),
            'sort' => $this->buildSort($request),
            'aggs' => [
                'categories' => ['terms' => ['field' => 'category_direct_ids', 'size' => 5]],
                'types' => ['terms' => ['field' => 'type_detail', 'size' => 5]],
            ],
            'suggest' => [
                'spelling' => [
                    'text' => $query,
                    'term' => ['field' => 'name', 'suggest_mode' => 'popular', 'min_word_length' => 5],
                ],
            ],
        ];
        if (!SkuQuery::looksLikeSku($query)) {
            $body['collapse'] = ['field' => self::COLLAPSE_FIELD]; // a typed SKU prefix lists every SKU
        }
        return $body;
    }

    public function buildBaseQuery(SearchRequest $request): array
    {
        $filter = [
            ['terms' => ['visibility' => $request->hasQuery() ? self::VISIBILITY_SEARCH : self::VISIBILITY_CATALOG]],
        ];
        if ($request->categoryId !== null) {
            $filter[] = ['term' => ['category_ids' => $request->categoryId]];
        }
        $bool = ['filter' => $filter];
        if ($request->hasQuery()) {
            $bool['must'] = [$this->buildTextQuery(trim((string)$request->query))];
        }
        return ['bool' => $bool];
    }

    public function buildTextQuery(string $query): array
    {
        $lower = mb_strtolower($query);
        $compact = SkuQuery::compact($query); // what the sku.compact normalizer stores
        $should = [
            ['term' => ['sku' => ['value' => $lower, 'boost' => self::BOOST_SKU_EXACT]]],
        ];
        if ($compact !== '' && $compact !== $lower) {
            $should[] = ['term' => ['sku.compact' => ['value' => $compact, 'boost' => self::BOOST_SKU_COMPACT]]];
        }
        if (SkuQuery::looksLikeSku($query)) {
            $should[] = ['prefix' => ['sku' => ['value' => $lower, 'boost' => self::BOOST_SKU_PREFIX]]];
            if ($compact !== '') {
                $should[] = ['prefix' => ['sku.compact' => ['value' => $compact, 'boost' => self::BOOST_SKU_PREFIX]]];
            }
        }
        if (preg_match('/\s/', $query)) {
            // "ginger jar" in this order in the name beats the words spread over fields
            $should[] = ['match_phrase' => ['name' => ['query' => $query, 'boost' => self::BOOST_NAME_PHRASE]]];
        }
        $should[] = [
            'multi_match' => [
                'query' => $query,
                'type' => 'best_fields',
                'fields' => self::TEXT_FIELDS,
                'operator' => 'and',
                'tie_breaker' => 0.3,
                'fuzziness' => 'AUTO:5,8',
                'prefix_length' => 1,
                'boost' => 2,
            ],
        ];
        $should[] = [
            'multi_match' => [
                'query' => $query,
                'type' => 'cross_fields',
                'fields' => self::TEXT_FIELDS,
                'operator' => 'and',
            ],
        ];
        return ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
    }

    public function buildSort(SearchRequest $request): array
    {
        $sort = $request->sort;
        if ($sort === SearchRequest::SORT_POSITION && $request->categoryId === null) {
            $sort = $request->hasQuery() ? SearchRequest::SORT_RELEVANCE : SearchRequest::SORT_NAME;
        }
        if ($sort === SearchRequest::SORT_RELEVANCE && !$request->hasQuery()) {
            $sort = $request->categoryId !== null ? SearchRequest::SORT_POSITION : SearchRequest::SORT_NAME;
        }
        $dir = $this->direction($request->direction, $sort);

        $clauses = match ($sort) {
            SearchRequest::SORT_POSITION => [
                [
                    'category_positions.position' => [
                        'order' => $dir,
                        'missing' => '_last',
                        'nested' => [
                            'path' => 'category_positions',
                            'filter' => ['term' => ['category_positions.id' => $request->categoryId]],
                        ],
                    ],
                ],
            ],
            SearchRequest::SORT_NAME => [['name.sort' => ['order' => $dir]]],
            SearchRequest::SORT_PRICE => [['price' => ['order' => $dir, 'missing' => '_last']]],
            SearchRequest::SORT_NEWEST => [['news_from_date' => ['order' => $dir, 'missing' => '_last']]],
            default => [['_score' => ['order' => $dir]]],
        };
        if ($sort !== SearchRequest::SORT_RELEVANCE && $request->hasQuery()) {
            $clauses[] = ['_score' => ['order' => 'desc']];
        }
        // Ties as in Magento's listing (product id). The member shown for a family is the first one by this sort,
        // so it is the best match for the query and always one that passes the filters.
        $clauses[] = ['id' => ['order' => $sort === SearchRequest::SORT_NEWEST ? 'desc' : 'asc']];
        return $clauses;
    }

    /**
     * @param FacetDefinition[] $facets
     * @return array<string, array> facet code => filter clause, only for facets with a selection
     */
    public function buildFacetFilters(SearchRequest $request, array $facets): array
    {
        $filters = [];
        foreach ($facets as $facet) {
            $clause = match ($facet->type) {
                Facet::TYPE_RANGE => $this->rangeClause($facet->code, $request->ranges[$facet->code] ?? null),
                Facet::TYPE_BOOLEAN => $this->booleanClause($facet->code, $request->filters[$facet->code] ?? []),
                default => $this->termsClause($facet->code, $request->filters[$facet->code] ?? []),
            };
            if ($clause !== null) {
                $filters[$facet->code] = $clause;
            }
        }
        return $filters;
    }

    private function buildFacetAgg(FacetDefinition $facet, bool $collapse): array
    {
        if ($facet->type === Facet::TYPE_RANGE) {
            return ['stats' => ['field' => $facet->code]];
        }
        $terms = [
            'field' => $facet->code,
            'size' => $facet->type === Facet::TYPE_BOOLEAN ? 2 : $facet->size,
        ];
        if ($facet->optionSort === FacetDefinition::SORT_LABEL) {
            $terms['order'] = ['_key' => 'asc'];
        }
        $agg = ['terms' => $terms];
        if ($collapse) {
            $agg['aggs'] = [self::AGG_ITEMS => $this->cardinality()];
        }
        return $agg;
    }

    private function termsClause(string $field, array $values): ?array
    {
        $values = array_values(array_unique(array_filter(
            array_map(static fn ($v): string => trim((string)$v), $values),
            static fn (string $v): bool => $v !== ''
        )));
        return $values === [] ? null : ['terms' => [$field => $values]];
    }

    private function booleanClause(string $field, array $values): ?array
    {
        foreach ($values as $value) {
            $value = strtolower(trim((string)$value));
            if (in_array($value, ['1', 'true', 'yes'], true)) {
                return ['term' => [$field => true]];
            }
            if (in_array($value, ['0', 'false', 'no'], true)) {
                return ['term' => [$field => false]];
            }
        }
        return null;
    }

    private function rangeClause(string $field, ?array $range): ?array
    {
        if ($range === null) {
            return null;
        }
        $bounds = [];
        if (isset($range['min']) && is_numeric($range['min'])) {
            $bounds['gte'] = (float)$range['min'];
        }
        if (isset($range['max']) && is_numeric($range['max'])) {
            $bounds['lte'] = (float)$range['max'];
        }
        return $bounds === [] ? null : ['range' => [$field => $bounds]];
    }

    private function combine(array $clauses): array
    {
        return $clauses === [] ? ['match_all' => (object)[]] : ['bool' => ['filter' => $clauses]];
    }

    private function cardinality(): array
    {
        return ['cardinality' => ['field' => self::COLLAPSE_FIELD, 'precision_threshold' => 40000]];
    }

    private function direction(?string $direction, string $sort): string
    {
        $direction = $direction !== null ? strtolower($direction) : null;
        if ($direction === 'asc' || $direction === 'desc') {
            return $direction;
        }
        return in_array($sort, [SearchRequest::SORT_RELEVANCE, SearchRequest::SORT_NEWEST], true) ? 'desc' : 'asc';
    }
}
