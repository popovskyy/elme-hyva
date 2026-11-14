<?php

declare(strict_types=1);

namespace Elme\Search\Controller\Listing;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Api\SearchUnavailableException;
use Elme\Search\Model\Config;
use Elme\Search\Model\SkuQuery;
use Elme\Search\Model\Storefront\RequestFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /elme-search/listing/count?category=11&q=&<facet params> - the filters drawer (FE-21) asks it while the
 * shopper ticks options, for the "Show N products" button and the option counts, before the URL changes.
 *
 * Facet parameters are the same as on the listing URL (RequestFactory). Response:
 * {"total": 12, "facets": {"<code>": {"<value>": 3}}, "ranges": {"<code>": {"min": 34, "max": 240}}}.
 * total is null when the engine is off or cannot answer (the drawer then shows "Show products").
 * No prices and nothing per customer, so the answer may be cached publicly; never a page for search engines.
 */
class Count implements HttpGetActionInterface
{
    private const MAX_QUERY_LENGTH = 128;
    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductSearchInterface $productSearch,
        private readonly RequestFactory $requestFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $data = ['total' => null, 'facets' => [], 'ranges' => []];

        $storeId = (int)$this->storeManager->getStore()->getId();
        $category = $this->request->getParam('category');
        $categoryId = is_scalar($category) && ctype_digit((string)$category) ? (int)$category : null;
        $query = $this->request->getParam('q');
        $query = is_scalar($query) ? trim(mb_substr((string)$query, 0, self::MAX_QUERY_LENGTH)) : '';

        if (!$this->config->isProductSearchEnabled($storeId) || ($categoryId === null && $query === '')) {
            $result->setHeader('Cache-Control', 'no-store', true);
            return $result->setData($data);
        }

        [$filters, $ranges] = $this->requestFactory->getFacetSelection();
        $searchRequest = new SearchRequest(
            $storeId,
            $query !== '' ? $query : null,
            $categoryId,
            $filters,
            $ranges,
            $query !== '' ? SearchRequest::SORT_RELEVANCE : SearchRequest::SORT_POSITION,
            null,
            1,
            1,
            !SkuQuery::looksLikeSku($query)
        );

        try {
            $search = $this->productSearch->search($searchRequest);
        } catch (SearchUnavailableException $e) {
            $this->logger->error('Elme_Search: filter count failed: ' . $e->getMessage());
            $result->setHeader('Cache-Control', 'no-store', true);
            return $result->setData($data);
        }

        $data['total'] = $search->total;
        foreach ($search->facets as $facet) {
            if ($facet->type === Facet::TYPE_RANGE) {
                $data['ranges'][$facet->code] = ['min' => $facet->min, 'max' => $facet->max];
                continue;
            }
            $counts = [];
            foreach ($facet->options as $option) {
                $counts[$option->value] = $option->count;
            }
            $data['facets'][$facet->code] = (object)$counts;
        }
        $data['facets'] = (object)$data['facets'];
        $data['ranges'] = (object)$data['ranges'];

        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_SECONDS, true);
        $result->setHeader('Pragma', 'cache', true);
        return $result->setData($data);
    }
}
