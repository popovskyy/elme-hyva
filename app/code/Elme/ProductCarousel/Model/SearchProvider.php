<?php

declare(strict_types=1);

namespace Elme\ProductCarousel\Model;

use Elme\ProductCarousel\Api\ProviderInterface;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\ProductSearchInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * FE-17 default source (decision D1: no Algolia).
 *
 * - trending: the products of the "Trending products" category (Stores > Configuration > Elme > Catalog), in the
 *   position the merchandiser gave them. No category set: nothing, the block hides.
 * - new: newest first by news_from_date, twice the limit, because the ones whose "new" window is over (or that have
 *   no date) sort last or are not new today; the block keeps only products the card marks New (ProductTile::isNew).
 * - category: the products of the given category, in its position order.
 * - related: Magento related products of the given product (admin: Related Products), until the Search Service
 *   offers recommendations.
 *
 * The first three go through ProductSearchInterface, so they follow whichever engine answers it.
 */
class SearchProvider implements ProviderInterface
{
    public const XML_PATH_TRENDING_CATEGORY = 'elme_catalog/product_carousel/trending_category_id';

    public function __construct(
        private readonly ProductSearchInterface $productSearch,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getProductIds(string $source, int $storeId, int $limit, array $options = []): array
    {
        if ($limit < 1) {
            return [];
        }

        try {
            return match ($source) {
                self::SOURCE_TRENDING => $this->fromCategory(
                    $storeId,
                    (int) $this->scopeConfig->getValue(
                        self::XML_PATH_TRENDING_CATEGORY,
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    ),
                    $limit
                ),
                self::SOURCE_CATEGORY => $this->fromCategory($storeId, (int) ($options['category_id'] ?? 0), $limit),
                self::SOURCE_NEW => $this->newest($storeId, $limit),
                self::SOURCE_RELATED => $this->related((int) ($options['product_id'] ?? 0), $limit),
                default => [],
            };
        } catch (\Throwable $exception) {
            // The search engine being down must not break the page: the block just does not show
            $this->logger->warning('Elme_ProductCarousel: ' . $source . ' source failed: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * @return int[]
     */
    private function fromCategory(int $storeId, int $categoryId, int $limit): array
    {
        if ($categoryId < 1) {
            return [];
        }

        $result = $this->productSearch->search(new SearchRequest(
            storeId: $storeId,
            categoryId: $categoryId,
            sort: SearchRequest::SORT_POSITION,
            pageSize: $limit
        ));

        return $this->ids($result->hits, $limit);
    }

    /**
     * @return int[]
     */
    private function newest(int $storeId, int $limit): array
    {
        $result = $this->productSearch->search(new SearchRequest(
            storeId: $storeId,
            sort: SearchRequest::SORT_NEWEST,
            pageSize: $limit * 2
        ));

        return $this->ids($result->hits, $limit * 2);
    }

    /**
     * @return int[]
     */
    private function related(int $productId, int $limit): array
    {
        if ($productId < 1) {
            return [];
        }

        $ids = array_map('intval', $this->productRepository->getById($productId)->getRelatedProductIds());

        return array_slice(array_values(array_unique(array_filter($ids))), 0, $limit);
    }

    /**
     * @param \Elme\Search\Api\Data\SearchHit[] $hits
     * @return int[]
     */
    private function ids(array $hits, int $limit): array
    {
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->productId;
        }

        return array_slice(array_values(array_unique($ids)), 0, $limit);
    }
}
