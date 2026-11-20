<?php

declare(strict_types=1);

namespace Elme\Search\Model\Indexer;

use Magento\Framework\Indexer\IndexerRegistry;
use Psr\Log\LoggerInterface;

/**
 * "Update on Save" for elme_search_products: Magento only reindexes its own indexers on product save, so admin
 * saves, deletes and mass actions call this. Skipped when the indexer is "Update by Schedule" (mview does it).
 * A failure is logged, never thrown: saving a product must not fail because search is down.
 */
class RealtimeUpdater
{
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly ProductIndexer $productIndexer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int[] $productIds
     */
    public function reindex(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return;
        }
        try {
            if ($this->indexerRegistry->get(ProductIndexer::INDEXER_ID)->isScheduled()) {
                return;
            }
            $this->productIndexer->reindexIds($productIds);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Elme_Search: could not update the search index for products ' . implode(',', $productIds)
                . ': ' . $e->getMessage()
            );
        }
    }
}
