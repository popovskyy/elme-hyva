<?php

declare(strict_types=1);

namespace Elme\Search\Observer;

use Elme\Search\Model\Indexer\RealtimeUpdater;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * catalog_product_save_commit_after / catalog_product_delete_commit_after: update that product's document
 * (a deleted or disabled product is removed from the index).
 */
class ReindexProduct implements ObserverInterface
{
    public function __construct(private readonly RealtimeUpdater $updater)
    {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product') ?: $observer->getEvent()->getData('data_object');
        if ($product && $product->getId()) {
            $this->updater->reindex([(int)$product->getId()]);
        }
    }
}
