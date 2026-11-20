<?php

declare(strict_types=1);

namespace Elme\ProductCarousel\Model;

use Elme\ProductCarousel\Api\ProviderInterface;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\ProductSearchInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
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
 * - related / cross_sell / upsell: the links the merchandiser sets in the product admin (Related Products,
 *   Cross-sell, Up-sell), until the Search Service offers recommendations.
 * - range: the other products of the same `range` (Shop the Range), one per family, never the product itself.
 * - similar: other products of the same type (Similar Products): `product_type` when the catalogue has it, the
 *   attribute set until then (the same rule as Elme\Theme\ViewModel\ProductDetails::getProductTypeCode, FE-29),
 *   one per family, leaving out the product's own range (that is the Shop the Range tab).
 *
 * The first three go through ProductSearchInterface, so they follow whichever engine answers it. range and similar
 * read the catalogue directly: the search index has neither `range` nor `product_type` as a facet yet (BE-24 owns
 * that); when it does, point these two at ProductSearchInterface and nothing outside this class changes.
 */
class SearchProvider implements ProviderInterface
{
    public const XML_PATH_TRENDING_CATEGORY = 'elme_catalog/product_carousel/trending_category_id';

    /** Rows read for range / similar before the families are collapsed to one card each */
    private const ATTRIBUTE_SCAN_FACTOR = 6;
    private const ATTRIBUTE_SCAN_MAX = 150;

    public function __construct(
        private readonly ProductSearchInterface $productSearch,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly EavConfig $eavConfig,
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
                self::SOURCE_RELATED,
                self::SOURCE_CROSS_SELL,
                self::SOURCE_UPSELL => $this->linked($source, (int) ($options['product_id'] ?? 0), $limit),
                self::SOURCE_RANGE,
                self::SOURCE_SIMILAR => $this->sameAttribute($source, $storeId, (int) ($options['product_id'] ?? 0), $limit),
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
    private function linked(string $source, int $productId, int $limit): array
    {
        if ($productId < 1) {
            return [];
        }

        $product = $this->productRepository->getById($productId);
        $ids = match ($source) {
            self::SOURCE_CROSS_SELL => $product->getCrossSellProductIds(),
            self::SOURCE_UPSELL => $product->getUpSellProductIds(),
            default => $product->getRelatedProductIds(),
        };

        return array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $ids)))), 0, $limit);
    }

    /**
     * Enabled, catalog-visible products of the store's website that share the product's range (or type), the
     * first of each family, in id order. Nothing when the product has no value for it.
     *
     * @return int[]
     */
    private function sameAttribute(string $source, int $storeId, int $productId, int $limit): array
    {
        if ($productId < 1) {
            return [];
        }

        $product = $this->productRepository->getById($productId, false, $storeId);
        if (!$product instanceof Product) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect(['family_code'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]])
            ->addFieldToFilter('entity_id', ['neq' => $productId])
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(min($limit * self::ATTRIBUTE_SCAN_FACTOR, self::ATTRIBUTE_SCAN_MAX));

        if ($source === self::SOURCE_RANGE) {
            $range = $product->getData('range');
            if ($range === null || $range === '') {
                return [];
            }
            $collection->addAttributeToFilter('range', $range);
        } else {
            $typeAttribute = $this->eavConfig->getAttribute(Product::ENTITY, 'product_type');
            if ($typeAttribute && $typeAttribute->getId() && $product->getData('product_type') !== null) {
                $collection->addAttributeToFilter('product_type', $product->getData('product_type'));
            } else {
                $collection->addFieldToFilter('attribute_set_id', (int) $product->getAttributeSetId());
            }
            $range = $product->getData('range');
            if ($range !== null && $range !== '') {
                // The Shop the Range tab already shows these
                $collection->addAttributeToFilter('range', ['neq' => $range]);
            }
        }

        // Other colours / sizes of this product are not recommendations: one card per family, never its own
        $seenFamilies = [];
        $ownFamily = (string) $product->getData('family_code');
        if ($ownFamily !== '') {
            $seenFamilies[$ownFamily] = true;
        }

        $ids = [];
        foreach ($collection as $item) {
            $family = (string) $item->getData('family_code');
            if ($family !== '') {
                if (isset($seenFamilies[$family])) {
                    continue;
                }
                $seenFamilies[$family] = true;
            }
            $ids[] = (int) $item->getId();
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
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
