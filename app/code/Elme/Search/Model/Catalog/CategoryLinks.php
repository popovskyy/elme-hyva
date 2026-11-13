<?php

declare(strict_types=1);

namespace Elme\Search\Model\Catalog;

use Elme\Search\Api\Data\SuggestCategory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Category ids from the index -> name and storefront URL (active categories of the store only).
 */
class CategoryLinks
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @param int[] $ids in the order to keep
     * @return SuggestCategory[]
     */
    public function get(array $ids, int $storeId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addAttributeToFilter('entity_id', ['in' => $ids])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('level', ['gt' => 1])
            ->addUrlRewriteToResult();

        $byId = [];
        foreach ($collection as $category) {
            $byId[(int)$category->getId()] = new SuggestCategory(
                (int)$category->getId(),
                (string)$category->getName(),
                (string)$category->getUrl()
            );
        }
        $result = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $result[] = $byId[$id];
            }
        }
        return $result;
    }
}
