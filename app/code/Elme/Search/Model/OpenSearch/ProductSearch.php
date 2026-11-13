<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\Data\SearchResult;
use Elme\Search\Api\Data\SuggestProduct;
use Elme\Search\Api\Data\SuggestResult;
use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Model\Catalog\CategoryLinks;
use Elme\Search\Model\Facet\FacetConfig;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ProductSearchInterface on our own OpenSearch index (elme_products_<store>).
 * Swapped for the Search Service adapter with one preference in etc/di.xml.
 */
class ProductSearch implements ProductSearchInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexName $indexName,
        private readonly QueryBuilder $queryBuilder,
        private readonly ResponseMapper $responseMapper,
        private readonly FacetConfig $facetConfig,
        private readonly CategoryLinks $categoryLinks,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlBuilder $imageUrlBuilder
    ) {
    }

    public function search(SearchRequest $request): SearchResult
    {
        $facets = $this->facetConfig->getFacets();
        $response = $this->client->search(
            $this->indexName->getAlias($request->storeId),
            $this->queryBuilder->build($request, $facets)
        );
        return $this->responseMapper->map($response, $request, $facets);
    }

    public function suggest(string $query, int $limit, int $storeId): SuggestResult
    {
        $query = trim($query);
        if ($query === '') {
            return new SuggestResult();
        }
        $response = $this->client->search(
            $this->indexName->getAlias($storeId),
            $this->queryBuilder->buildSuggest($query, $limit)
        );

        $store = $this->storeManager->getStore($storeId);
        $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

        $products = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            if (empty($source['id'])) {
                continue;
            }
            $image = isset($source['image']) && $source['image'] !== '' && $source['image'] !== 'no_selection'
                ? $this->imageUrl((string)$source['image'], $mediaUrl) : null;
            $products[] = new SuggestProduct(
                (int)$source['id'],
                (string)($source['sku'] ?? ''),
                (string)($source['name'] ?? ''),
                $baseUrl . ltrim((string)($source['url'] ?? ''), '/'),
                $image
            );
        }

        $categoryIds = array_column($response['aggregations']['categories']['buckets'] ?? [], 'key');
        $categories = $this->categoryLinks->get($categoryIds, $storeId);

        return new SuggestResult($products, $categories, $this->querySuggestions($query, $response));
    }

    /**
     * Spelling correction first (when OpenSearch has one), then the types of the matching products.
     *
     * @return string[]
     */
    private function querySuggestions(string $query, array $response): array
    {
        $suggestions = [];
        $corrected = $query;
        $changed = false;
        foreach ($response['suggest']['spelling'] ?? [] as $token) {
            $option = $token['options'][0]['text'] ?? null;
            if ($option !== null && isset($token['text'])) {
                $corrected = str_ireplace((string)$token['text'], (string)$option, $corrected);
                $changed = true;
            }
        }
        if ($changed && mb_strtolower($corrected) !== mb_strtolower($query)) {
            $suggestions[] = $corrected;
        }
        foreach ($response['aggregations']['types']['buckets'] ?? [] as $bucket) {
            $type = (string)$bucket['key'];
            if ($type !== '' && mb_strtolower($type) !== mb_strtolower($query)) {
                $suggestions[] = $type;
            }
        }
        return array_slice(array_values(array_unique($suggestions)), 0, 5);
    }

    private function imageUrl(string $path, string $mediaUrl): string
    {
        try {
            return $this->imageUrlBuilder->getUrl($path, 'product_thumbnail_image');
        } catch (\Throwable) {
            return $mediaUrl . '/' . ltrim($path, '/');
        }
    }
}
