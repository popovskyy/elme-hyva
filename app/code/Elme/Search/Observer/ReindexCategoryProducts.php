<?php

declare(strict_types=1);

namespace Elme\Search\Observer;

use Elme\Search\Model\Indexer\RealtimeUpdater;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * catalog_category_save_commit_after: products added, removed or moved (position) in a category.
 */
class ReindexCategoryProducts implements ObserverInterface
{
    public function __construct(private readonly RealtimeUpdater $updater)
    {
    }

    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getData('category') ?: $observer->getEvent()->getData('data_object');
        if (!$category) {
            return;
        }
        $ids = (array)$category->getAffectedProductIds();
        if ($category->getData('is_changed_product_list') && $ids === []) {
            $ids = array_keys((array)$category->getPostedProducts());
        }
        $this->updater->reindex($ids);
    }
}
