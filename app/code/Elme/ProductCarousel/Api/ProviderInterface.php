<?php

declare(strict_types=1);

namespace Elme\ProductCarousel\Api;

/**
 * Where a product carousel gets its products from (FE-17).
 *
 * One interface so the source can change without touching the block or the templates: today
 * Model\SearchProvider (our ProductSearchInterface for trending / new / a category, Magento related products for
 * related); later a class that asks the backend Search Service for recommendations, switched by one di.xml
 * preference. Returns product ids in display order, an empty list when there is nothing to show (the block then
 * renders nothing, title included). Normally at most $limit ids; "new" may return up to twice that, because the block
 * drops the ones that are no longer new and then keeps the first $limit. Never throws.
 */
interface ProviderInterface
{
    public const SOURCE_TRENDING = 'trending';
    public const SOURCE_NEW = 'new';
    public const SOURCE_CATEGORY = 'category';
    public const SOURCE_RELATED = 'related';

    public const SOURCES = [
        self::SOURCE_TRENDING,
        self::SOURCE_NEW,
        self::SOURCE_CATEGORY,
        self::SOURCE_RELATED,
    ];

    /**
     * @param string $source one of SOURCES
     * @param int $storeId store view
     * @param int $limit most products wanted
     * @param array{category_id?: int, product_id?: int} $options category_id for "category", product_id for
     *        "related" (the product the page is about)
     * @return int[] product ids, in display order
     */
    public function getProductIds(string $source, int $storeId, int $limit, array $options = []): array;
}
