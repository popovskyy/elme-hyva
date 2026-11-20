<?php

declare(strict_types=1);

namespace Elme\Search\Api;

use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\Data\SearchResult;
use Elme\Search\Api\Data\SuggestResult;

/**
 * Product search, listings and facets for the storefront.
 *
 * The contract the storefront talks to. Today it is answered by our own OpenSearch index
 * (Elme\Search\Model\OpenSearch\ProductSearch); later the backend Search Service answers it through a new class
 * and one di.xml preference. Plain data objects only: no Magento collections, no engine terms.
 *
 * Implementations throw SearchUnavailableException when the engine cannot answer; callers fall back.
 */
interface ProductSearchInterface
{
    /**
     * Listing or search results: product ids and SKUs of one page in order, the total and the facets.
     *
     * Families (family_code) collapse to one item: the best member for the request (it matches the filters).
     * Total and facet counts count those items, not the members.
     *
     * @throws SearchUnavailableException
     */
    public function search(SearchRequest $request): SearchResult;

    /**
     * Autocomplete: a few products, categories that hold matching products and query suggestions.
     *
     * @throws SearchUnavailableException
     */
    public function suggest(string $query, int $limit, int $storeId): SuggestResult;
}
