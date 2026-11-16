<?php

declare(strict_types=1);

namespace Elme\Search\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Shop popular categories" on the empty search results page (FE-23).
 *
 * There is no popularity data, so these are the main categories of the store, the ones in the main menu, in menu
 * order. Nothing here depends on the search engine.
 */
class PopularCategories implements ArgumentInterface
{
    public const LIMIT = 8;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function getCategories(): array
    {
        $store = $this->storeManager->getStore();
        $collection = $this->collectionFactory->create();
        $collection->setStoreId((int)$store->getId())
            ->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addAttributeToFilter('parent_id', (int)$store->getRootCategoryId())
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addUrlRewriteToResult()
            ->setOrder('position', 'ASC')
            ->setPageSize(self::LIMIT);

        $categories = [];
        /** @var Category $category */
        foreach ($collection as $category) {
            $name = trim((string)$category->getName());
            $url = (string)$category->getUrl();
            if ($name !== '' && $url !== '') {
                $categories[] = ['name' => $name, 'url' => $url];
            }
        }
        return $categories;
    }
}
