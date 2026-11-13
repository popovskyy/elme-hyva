<?php

declare(strict_types=1);

namespace Elme\Search\Model\Storefront;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Facet\FacetConfig;
use Elme\Search\Model\SkuQuery;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Magento's search criteria of the category / search page (category, query text, page, page size, toolbar sort)
 * plus our facet filters from the URL -> SearchRequest.
 *
 * Facet URL parameters (FE-21 builds them): ?<code>=value1,value2 (multi), ?in_stock=1 (boolean),
 * ?width=50-120 / ?width=50- / ?width=-120 (range).
 */
class RequestFactory
{
    /** Toolbar sort codes -> SearchRequest sorts */
    private const SORT_MAP = [
        'relevance' => SearchRequest::SORT_RELEVANCE,
        'position' => SearchRequest::SORT_POSITION,
        'name' => SearchRequest::SORT_NAME,
        'price' => SearchRequest::SORT_PRICE,
        'newest' => SearchRequest::SORT_NEWEST,
        'news_from_date' => SearchRequest::SORT_NEWEST,
        'created_at' => SearchRequest::SORT_NEWEST,
    ];

    private const MAX_PAGE_SIZE = 10000;

    public function __construct(
        private readonly RequestInterface $httpRequest,
        private readonly FacetConfig $facetConfig
    ) {
    }

    public function fromCriteria(SearchCriteriaInterface $criteria, int $storeId): SearchRequest
    {
        $categoryId = null;
        $query = null;
        foreach ($criteria->getFilterGroups() as $group) {
            foreach ($group->getFilters() as $filter) {
                $value = $filter->getValue();
                if ($filter->getField() === 'category_ids') {
                    $value = is_array($value) ? reset($value) : $value;
                    $categoryId = is_numeric($value) ? (int)$value : null;
                } elseif ($filter->getField() === 'search_term') {
                    $query = trim((string)$value);
                }
            }
        }

        $sort = null;
        $direction = null;
        foreach ((array)$criteria->getSortOrders() as $field => $dir) {
            if (is_object($dir)) { // SortOrder objects
                $field = (string)$dir->getField();
                $dir = (string)$dir->getDirection();
            }
            if (isset(self::SORT_MAP[$field])) {
                $sort = self::SORT_MAP[$field];
                $direction = strtolower((string)$dir) === 'desc' ? 'desc' : 'asc';
                break;
            }
        }
        if ($sort === null) {
            $sort = $query !== null && $query !== '' ? SearchRequest::SORT_RELEVANCE : SearchRequest::SORT_POSITION;
        }
        if ($sort === SearchRequest::SORT_RELEVANCE) {
            $direction = 'desc'; // the toolbar sends ASC for relevance; best match first is the only sensible order
        }

        $pageSize = (int)$criteria->getPageSize();
        $pageSize = $pageSize > 0 ? min($pageSize, self::MAX_PAGE_SIZE) : self::MAX_PAGE_SIZE;

        [$filters, $ranges] = $this->getFacetSelection();

        return new SearchRequest(
            $storeId,
            $query,
            $categoryId,
            $filters,
            $ranges,
            $sort,
            $direction,
            max(1, (int)$criteria->getCurrentPage() + 1),
            $pageSize,
            // a SKU search lists every matching SKU; collapsing would hide the siblings a trade customer typed
            !SkuQuery::looksLikeSku($query)
        );
    }

    /**
     * @return array{0: array<string, string[]>, 1: array<string, array{min: ?float, max: ?float}>}
     */
    public function getFacetSelection(): array
    {
        $filters = [];
        $ranges = [];
        foreach ($this->facetConfig->getFacets() as $facet) {
            $param = $this->httpRequest->getParam($facet->code);
            if ($param === null || $param === '' || $param === []) {
                continue;
            }
            if ($facet->type === Facet::TYPE_RANGE) {
                $range = $this->parseRange($param);
                if ($range !== null) {
                    $ranges[$facet->code] = $range;
                }
                continue;
            }
            $values = is_array($param) ? $param : explode(',', (string)$param);
            $values = array_values(array_filter(
                array_map(static fn ($v): string => trim((string)$v), $values),
                static fn (string $v): bool => $v !== ''
            ));
            if ($values !== []) {
                $filters[$facet->code] = $values;
            }
        }
        return [$filters, $ranges];
    }

    private function parseRange(mixed $param): ?array
    {
        if (is_array($param)) {
            $min = $param['min'] ?? null;
            $max = $param['max'] ?? null;
        } else {
            $parts = explode('-', (string)$param, 2);
            $min = $parts[0] ?? null;
            $max = $parts[1] ?? null;
        }
        $min = is_numeric($min) ? (float)$min : null;
        $max = is_numeric($max) ? (float)$max : null;
        return $min === null && $max === null ? null : ['min' => $min, 'max' => $max];
    }
}
