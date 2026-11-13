<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Model\Config;
use Elme\Search\Model\Storefront\CurrentSearch;
use Elme\Search\Model\Storefront\EmptyAggregation;
use Elme\Search\Model\Storefront\RequestFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Search\Api\SearchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * With elme_search/general/engine = opensearch, the category page and the search results page take product ids,
 * order and total from ProductSearchInterface. The answer is handed back to Magento as its own search result, so
 * the product collection loads exactly those ids in that order and the toolbar / pager work unchanged.
 * With engine = magento this plugin does nothing.
 *
 * If ProductSearchInterface fails, the page falls back to Magento's search and one error is logged.
 */
class UseProductSearch
{
    private const REQUEST_NAMES = ['catalog_view_container', 'quick_search_container'];

    public function __construct(
        private readonly Config $config,
        private readonly ProductSearchInterface $productSearch,
        private readonly RequestFactory $requestFactory,
        private readonly CurrentSearch $currentSearch,
        private readonly StoreManagerInterface $storeManager,
        private readonly SearchResultFactory $searchResultFactory,
        private readonly DocumentFactory $documentFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundSearch(
        SearchInterface $subject,
        callable $proceed,
        SearchCriteriaInterface $searchCriteria
    ): SearchResultInterface {
        if (!in_array($searchCriteria->getRequestName(), self::REQUEST_NAMES, true)
            || $this->currentSearch->hasFailed()
        ) {
            return $proceed($searchCriteria);
        }
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isProductSearchEnabled($storeId)) {
            return $proceed($searchCriteria);
        }

        try {
            $request = $this->requestFactory->fromCriteria($searchCriteria, $storeId);
            $result = $this->productSearch->search($request);
        } catch (\Throwable $e) {
            $this->currentSearch->markFailed();
            $this->logger->error(
                'Elme_Search: ProductSearchInterface failed, falling back to Magento catalog search: '
                . $e->getMessage()
            );
            return $proceed($searchCriteria);
        }
        $this->currentSearch->set($request, $result);

        $documents = [];
        foreach ($result->getProductIds() as $productId) {
            $document = $this->documentFactory->create();
            $document->setId($productId);
            $documents[] = $document;
        }
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($documents);
        $searchResult->setTotalCount($result->total);
        $searchResult->setAggregations(new EmptyAggregation());
        $searchResult->setSearchCriteria($searchCriteria);
        return $searchResult;
    }
}
