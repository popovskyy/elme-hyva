<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Model\Indexer\RealtimeUpdater;
use Magento\Catalog\Model\Product\Action;

/**
 * Admin mass actions (Update attributes, change status, websites) do not save products one by one.
 */
class ReindexOnMassAction
{
    public function __construct(private readonly RealtimeUpdater $updater)
    {
    }

    public function afterUpdateAttributes(Action $subject, $result, $productIds)
    {
        $this->updater->reindex((array)$productIds);
        return $result;
    }

    public function afterUpdateWebsites(Action $subject, $result, $productIds)
    {
        $this->updater->reindex((array)$productIds);
        return $result;
    }
}
